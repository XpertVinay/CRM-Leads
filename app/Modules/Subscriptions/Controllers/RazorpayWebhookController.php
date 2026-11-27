<?php

namespace App\Modules\Subscriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Services\RazorpayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function receive(Request $request, RazorpayWebhookService $webhookService): JsonResponse
    {
        $result = $webhookService->handle(
            rawBody: (string) $request->getContent(),
            headers: $request->headers->all(),
            ipAddress: $request->ip(),
        );

        return response()->json($result['body'], $result['http_status']);
    }
}
