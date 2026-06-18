<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            default:
                Log::info('Stripe webhook: Unhandled event type', [
                    'type' => $event->type,
                ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle checkout.session.completed event.
     */
    protected function handleCheckoutSessionCompleted($session): void
    {
        $customerId = $session->customer;
        $user = User::where('stripe_customer_id', $customerId)->first();

        if (!$user) {
            Log::warning('Stripe webhook: User not found for customer', [
                'customer_id' => $customerId,
            ]);
            return;
        }

        if ($session->mode === 'subscription' && $session->subscription) {
            $this->syncSubscription($session->subscription, $user);
        }
    }

    /**
     * Handle customer.subscription.created/updated events.
     */
    protected function handleSubscriptionUpdated($subscription): void
    {
        $customerId = $subscription->customer;
        $user = User::where('stripe_customer_id', $customerId)->first();

        if (!$user) {
            Log::warning('Stripe webhook: User not found for subscription', [
                'customer_id' => $customerId,
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $this->syncSubscription($subscription->id, $user);
    }

    /**
     * Handle customer.subscription.deleted event.
     */
    protected function handleSubscriptionDeleted($subscription): void
    {
        $subscriptionModel = Subscription::where('stripe_subscription_id', $subscription->id)->first();

        if ($subscriptionModel) {
            $subscriptionModel->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            $user = $subscriptionModel->user;
            $user->update([
                'subscription_status' => 'canceled',
                'subscription_ends_at' => now(),
            ]);

            Log::info('Stripe webhook: Subscription canceled', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Handle invoice.payment_succeeded event.
     */
    protected function handleInvoicePaymentSucceeded($invoice): void
    {
        $customerId = $invoice->customer;
        $user = User::where('stripe_customer_id', $customerId)->first();

        if ($user && $invoice->subscription) {
            $this->syncSubscription($invoice->subscription, $user);
        }
    }

    /**
     * Handle invoice.payment_failed event.
     */
    protected function handleInvoicePaymentFailed($invoice): void
    {
        $customerId = $invoice->customer;
        $user = User::where('stripe_customer_id', $customerId)->first();

        if ($user) {
            $user->update([
                'subscription_status' => 'past_due',
            ]);

            Log::info('Stripe webhook: Payment failed', [
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    /**
     * Sync subscription data from Stripe.
     */
    protected function syncSubscription(string $subscriptionId, User $user): void
    {
        try {
            $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);

            $subscription = Subscription::updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscription->id],
                [
                    'user_id' => $user->id,
                    'stripe_price_id' => $stripeSubscription->items->data[0]->price->id,
                    'status' => $stripeSubscription->status,
                    'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
                    'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                ]
            );

            // Update user subscription status
            $isActive = $stripeSubscription->status === 'active' 
                && $stripeSubscription->current_period_end > time();

            $user->update([
                'subscription_status' => $isActive ? 'active' : $stripeSubscription->status,
                'subscription_ends_at' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
            ]);

            Log::info('Stripe webhook: Subscription synced', [
                'user_id' => $user->id,
                'subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: Failed to sync subscription', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
