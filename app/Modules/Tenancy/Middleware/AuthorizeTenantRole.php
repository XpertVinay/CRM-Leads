<?php

namespace App\Modules\Tenancy\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeTenantRole
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $user = $request->user();

        if (! $tenant || ! $user instanceof User) {
            abort(403, 'Tenant authorization context is missing.');
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        $role = $user->roleIn($tenant);

        if ($role === null) {
            abort(403, 'User is not a member of the active organization.');
        }

        if (! empty($roles) && ! in_array($role, $roles, true)) {
            abort(403, 'Insufficient organization role permissions.');
        }

        return $next($request);
    }
}
