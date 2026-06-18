<?php

namespace App\Services;

use App\Models\User;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get or create Stripe customer for user.
     */
    public function getOrCreateCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        try {
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $user->update([
                'stripe_customer_id' => $customer->id,
            ]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            \Log::error('Stripe customer creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create checkout session for subscription.
     */
    public function createCheckoutSession(User $user, string $priceId): Session
    {
        $customerId = $this->getOrCreateCustomer($user);

        try {
            return Session::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => config('app.frontend_url') . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/subscription/cancel',
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe checkout session creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(string $subscriptionId): Subscription
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            return $subscription->cancel();
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resume subscription.
     */
    public function resumeSubscription(string $subscriptionId): Subscription
    {
        try {
            $subscription = Subscription::retrieve($subscriptionId);
            return $subscription->resume();
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription resume failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve subscription from Stripe.
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        try {
            return Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe subscription retrieval failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
