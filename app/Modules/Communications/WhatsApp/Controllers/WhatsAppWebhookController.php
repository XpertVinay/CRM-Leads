<?php

namespace App\Modules\Communications\WhatsApp\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\WhatsApp\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expectedToken = (string) config('services.meta.webhook_verify_token', '');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        $appSecret = (string) config('services.meta.app_secret', '');
        $signatureHeader = (string) $request->header('X-Hub-Signature-256', '');

        if ($appSecret === '' || $signatureHeader === '' || ! str_starts_with($signatureHeader, 'sha256=')) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $rawBody = (string) $request->getContent();
        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        ProcessWhatsAppWebhookJob::dispatch($payload);

        return response()->json(['status' => 'accepted'], 200);
    }
}
