<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Get user's subscription status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSubscription = $user->activeSubscription();

        return response()->json([
            'subscription_status' => $user->subscription_status,
            'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
            'has_active_subscription' => $user->hasActiveSubscription(),
            'is_on_trial' => $user->isOnTrial(),
            'can_access_premium_content' => $user->canAccessPremiumContent(),
            'active_subscription' => $activeSubscription ? [
                'id' => $activeSubscription->id,
                'stripe_subscription_id' => $activeSubscription->stripe_subscription_id,
                'status' => $activeSubscription->status,
                'current_period_start' => $activeSubscription->current_period_start->toIso8601String(),
                'current_period_end' => $activeSubscription->current_period_end->toIso8601String(),
                'cancel_at_period_end' => $activeSubscription->cancel_at_period_end,
            ] : null,
        ]);
    }

    /**
     * Create checkout session for subscription.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'price_id' => ['required', 'string'],
        ]);

        $user = $request->user();
        $priceId = $request->price_id;

        try {
            $session = $this->stripeService->createCheckoutSession($user, $priceId);

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la création de la session de paiement.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSubscription = $user->activeSubscription();

        if (!$activeSubscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif trouvé.',
            ], 404);
        }

        try {
            $this->stripeService->cancelSubscription($activeSubscription->stripe_subscription_id);

            $activeSubscription->update([
                'cancel_at_period_end' => true,
                'canceled_at' => now(),
            ]);

            return response()->json([
                'message' => 'Abonnement annulé avec succès. Il restera actif jusqu\'à la fin de la période en cours.',
                'subscription' => [
                    'status' => $activeSubscription->status,
                    'current_period_end' => $activeSubscription->current_period_end->toIso8601String(),
                    'cancel_at_period_end' => true,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'user_id' => $user->id,
                'subscription_id' => $activeSubscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'annulation de l\'abonnement.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Resume subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSubscription = $user->activeSubscription();

        if (!$activeSubscription || !$activeSubscription->cancel_at_period_end) {
            return response()->json([
                'message' => 'Aucun abonnement annulé trouvé.',
            ], 404);
        }

        try {
            $this->stripeService->resumeSubscription($activeSubscription->stripe_subscription_id);

            $activeSubscription->update([
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ]);

            return response()->json([
                'message' => 'Abonnement repris avec succès.',
                'subscription' => [
                    'status' => $activeSubscription->status,
                    'cancel_at_period_end' => false,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription resume failed', [
                'user_id' => $user->id,
                'subscription_id' => $activeSubscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la reprise de l\'abonnement.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
