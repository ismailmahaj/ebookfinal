<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * List all users with subscription status (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'subscription_status', 'subscription_ends_at', 'trial_ends_at', 'is_admin', 'created_at']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('subscription_status')) {
            if ($request->subscription_status === 'active') {
                $query->where('subscription_status', 'active')
                    ->whereNotNull('subscription_ends_at')
                    ->where('subscription_ends_at', '>', now());
            } elseif ($request->subscription_status === 'inactive') {
                $query->where(function ($q) {
                    $q->where('subscription_status', '!=', 'active')
                        ->orWhereNull('subscription_ends_at')
                        ->orWhere('subscription_ends_at', '<=', now());
                });
            }
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($request->get('per_page', 15));

        $users->getCollection()->transform(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'subscription_status' => $user->subscription_status,
                'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
                'trial_ends_at' => $user->trial_ends_at?->toIso8601String(),
                'has_active_subscription' => $user->hasActiveSubscription(),
                'is_on_trial' => $user->isOnTrial(),
                'is_admin' => $user->is_admin,
                'created_at' => $user->created_at->toIso8601String(),
            ];
        });

        return response()->json($users);
    }

    /**
     * Update a user's subscription status (admin only).
     */
    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'subscription_status' => ['required', 'string', 'in:active,inactive,canceled'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $user = User::findOrFail($id);

        $user->subscription_status = $request->subscription_status;
        if ($request->subscription_status === 'active') {
            $user->subscription_ends_at = $request->subscription_ends_at
                ? \Carbon\Carbon::parse($request->subscription_ends_at)->endOfDay()
                : now()->addMonth();
        } else {
            $user->subscription_ends_at = null;
        }
        $user->save();

        return response()->json([
            'message' => 'Statut d\'abonnement mis à jour.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'subscription_status' => $user->subscription_status,
                'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
                'has_active_subscription' => $user->hasActiveSubscription(),
            ],
        ]);
    }
}
