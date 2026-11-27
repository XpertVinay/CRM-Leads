<?php

namespace App\Modules\Subscriptions\Middleware;

use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperadmin()) {
            return $next($request);
        }

        if (
            $request->routeIs('subscription.*')
            || $request->routeIs('ajax.subscription.*')
            || $request->routeIs('ajax.coupons.*')
        ) {
            return $next($request);
        }

        if (! Schema::hasTable('subscriptions')) {
            return $next($request);
        }

        $organization = $this->tenantContext->getTenant();
        if ($organization === null) {
            return $next($request);
        }

        $latest = Subscription::query()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->first();

        if (! $latest instanceof Subscription) {
            return $next($request);
        }

        if ($latest->isOperational()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('ajax/*') || $request->is('api/*')) {
            return response()->json([
                'message' => 'An active subscription is required to access this workspace.',
                'subscription_status' => $latest->status,
                'redirect_url' => route('subscription.index'),
            ], 402);
        }

        return redirect()->route('subscription.index')
            ->with('warning', 'Please complete your subscription payment to unlock the CRM workspace.');
    }
}
