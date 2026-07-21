<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Admin dan Super Admin bebas dari cek subscription
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return $next($request);
        }

        // Cek subscription aktif
        $subscription = $user->subscription;

        if (! $subscription) {
            return redirect()->route('subscription.expired');
        }

        // Trial masih aktif
        if ($subscription->plan === 'trial') {
            if ($subscription->trial_ends_at && $subscription->trial_ends_at->isPast()) {
                return redirect()->route('subscription.expired');
            }

            return $next($request);
        }

        // Plan berbayar - cek status
        if ($subscription->status !== 'active') {
            return redirect()->route('subscription.expired');
        }

        return $next($request);
    }
}
