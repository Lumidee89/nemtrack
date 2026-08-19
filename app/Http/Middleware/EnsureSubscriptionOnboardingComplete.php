<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionOrder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === 'super_admin' || ! $user->organization_id) {
            return $next($request);
        }

        $pendingOrder = SubscriptionOrder::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $pendingOrder) {
            return $next($request);
        }

        $hasActiveModule = $user->organization?->modules()
            ->wherePivot('enabled', true)
            ->where('available', true)
            ->where(fn ($query) => $query->whereNull('organization_modules.expires_at')
                ->orWhere('organization_modules.expires_at', '>', now()))
            ->exists();

        return $hasActiveModule
            ? $next($request)
            : redirect()->route('subscriptions.checkout', $pendingOrder);
    }
}
