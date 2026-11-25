<?php

use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name', 'CRM'),
    ]);
})->name('api.health');

Route::middleware(['api.token'])->group(function (): void {
    Route::get('/me', function (Request $request, TenantContext $tenantContext): JsonResponse {
        $user = $request->user();
        $tenant = $tenantContext->getTenant();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'organization' => $tenant ? [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => $user->roleIn($tenant),
            ] : null,
        ]);
    })->name('api.me');
});

// Phase 4 placeholder: GET/POST /api/webhooks/whatsapp will be registered here.
