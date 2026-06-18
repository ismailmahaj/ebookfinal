<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentification requise.',
            ], 401);
        }

        if (!$user->canAccessPremiumContent()) {
            return response()->json([
                'message' => 'Un abonnement actif est requis pour accéder à cette ressource.',
                'subscription_status' => $user->subscription_status,
                'subscription_ends_at' => $user->subscription_ends_at?->toIso8601String(),
            ], 403);
        }

        return $next($request);
    }
}
