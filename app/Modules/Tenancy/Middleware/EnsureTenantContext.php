<?php

namespace App\Modules\Tenancy\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('ajax/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        $organization = $this->resolveOrganization($request, $user);

        if (! $organization instanceof Organization) {
            abort(403, 'No active organization context available for this user.');
        }

        $this->tenantContext->setTenant($organization);

        View::share('activeOrganization', $organization);
        View::share('activeRole', $user->roleIn($organization) ?? ($user->isSuperadmin() ? 'superadmin' : null));

        return $next($request);
    }

    private function resolveOrganization(Request $request, User $user): ?Organization
    {
        $headerOrgId = $request->header('X-Organization-ID');
        if ($headerOrgId !== null && $headerOrgId !== '') {
            if (! ctype_digit((string) $headerOrgId)) {
                abort(403, 'Invalid organization identifier.');
            }

            return $this->findAuthorizedActiveOrganization($user, (int) $headerOrgId);
        }

        if ($request->hasSession() && $request->session()->has('active_organization_id')) {
            $sessionOrgId = (int) $request->session()->get('active_organization_id');
            if ($sessionOrgId > 0) {
                return $this->findAuthorizedActiveOrganization($user, $sessionOrgId);
            }
        }

        $fallback = $user->organizations()
            ->where('organizations.status', 'active')
            ->orderBy('organizations.id')
            ->first();

        if ($fallback instanceof Organization && $request->hasSession()) {
            $request->session()->put('active_organization_id', (int) $fallback->id);
        }

        return $fallback;
    }

    private function findAuthorizedActiveOrganization(User $user, int $organizationId): Organization
    {
        $organization = Organization::query()->find($organizationId);

        if (! $organization instanceof Organization || ! $organization->isActive()) {
            abort(403, 'Organization is not active or does not exist.');
        }

        if (! $user->isSuperadmin() && $user->roleIn($organization) === null) {
            abort(403, 'You do not belong to the requested organization.');
        }

        return $organization;
    }
}
