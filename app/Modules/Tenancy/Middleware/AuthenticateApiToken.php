<?php

namespace App\Modules\Tenancy\Middleware;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || trim($bearer) === '') {
            return response()->json(['message' => 'Missing Bearer token.'], 401);
        }

        $hash = hash('sha256', $bearer);

        $apiToken = ApiToken::withoutGlobalScope(OrganizationScope::class)
            ->with(['organization', 'user'])
            ->where('token_hash', $hash)
            ->first();

        if (! $apiToken || ! $apiToken->isValid()) {
            return response()->json(['message' => 'Invalid or revoked API token.'], 401);
        }

        $organization = $apiToken->organization;
        $user = $apiToken->user;

        if (! $organization || ! $organization->isActive() || ! $user) {
            return response()->json(['message' => 'Token organization or user is inactive.'], 401);
        }

        if (! $user->isSuperadmin() && $user->roleIn($organization) === null) {
            return response()->json(['message' => 'User is no longer a member of this organization.'], 403);
        }

        $headerOrgId = $request->header('X-Organization-ID');
        if ($headerOrgId !== null && trim((string) $headerOrgId) !== '') {
            $trimmedHeaderOrgId = trim((string) $headerOrgId);
            if (! ctype_digit($trimmedHeaderOrgId) || (int) $trimmedHeaderOrgId <= 0) {
                return response()->json(['message' => 'Invalid X-Organization-ID header.'], 403);
            }

            if ((int) $trimmedHeaderOrgId !== (int) $organization->id) {
                return response()->json(['message' => 'API token is not authorized for the requested organization.'], 403);
            }
        }

        $apiToken->forceFill(['last_used_at' => now()])->saveQuietly();

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $this->tenantContext->setTenant($organization);

        return $next($request);
    }
}
