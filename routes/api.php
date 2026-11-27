<?php

use App\Modules\Communications\WhatsApp\Controllers\WhatsAppWebhookController;
use App\Modules\Subscriptions\Controllers\RazorpayWebhookController;
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

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('api.webhooks.whatsapp.verify');

Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:3000,1')
    ->name('api.webhooks.whatsapp.receive');

Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'receive'])
    ->middleware('throttle:300,1')
    ->name('api.webhooks.razorpay');
