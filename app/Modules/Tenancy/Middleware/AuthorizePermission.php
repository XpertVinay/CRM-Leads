<?php

namespace App\Modules\Tenancy\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePermission
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RbacService $rbacService,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $tenant = $this->tenantContext->getTenant();
        $user = $request->user();

        if (! $tenant || ! $user instanceof User) {
            abort(403, 'Tenant authorization context is missing.');
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName !== '' && ! $this->rbacService->isRouteAllowedByMenus($routeName, $user, $tenant)) {
            abort(403, 'Insufficient permissions to access this resource.');
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        if ($user->roleIn($tenant) === null) {
            abort(403, 'User is not a member of the active organization.');
        }

        foreach ($permissions as $permission) {
            if ($this->rbacService->hasPermission($user, $tenant, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'Insufficient permissions to access this resource.');
    }
}
