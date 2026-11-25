<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApiTokenController extends Controller
{
    public function store(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTokens', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:50'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $issued = ApiToken::issue(
            organization: $organization,
            user: $request->user(),
            name: $validated['name'],
            abilities: $validated['abilities'] ?? ['*'],
            expiresAt: isset($validated['expires_at']) ? now()->parse($validated['expires_at']) : null,
        );

        return response()->json([
            'message' => 'API token created. Copy the plain text token now; it will not be shown again.',
            'token' => $issued['token'],
            'plain_text_token' => $issued['plain_text_token'],
        ], 201);
    }

    public function destroy(ApiToken $token, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTokens', $organization);

        if ((int) $token->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $token->update(['revoked_at' => now()]);

        return response()->json([
            'message' => 'API token revoked.',
        ]);
    }
}
