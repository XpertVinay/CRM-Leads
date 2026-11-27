<?php

namespace App\Modules\Subscriptions\Services;

use App\Modules\Compliance\Services\AuditService;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RazorpayWebhookService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function handle(string $rawBody, array $headers, ?string $ipAddress = null): array
    {
        $decoded = json_decode($rawBody, true);
        $payload = is_array($decoded) ? $decoded : ['raw' => $rawBody];

        $eventName = (string) ($payload['event'] ?? 'unknown');
        $eventId = $this->headerValue($headers, 'x-razorpay-event-id');
        $signature = $this->headerValue($headers, 'x-razorpay-signature');
        $webhookSecret = (string) config('services.razorpay.webhook_secret');

        $subEntity = $payload['payload']['subscription']['entity'] ?? [];
        $payEntity = $payload['payload']['payment']['entity'] ?? [];
        $razorpaySubId = is_string($subEntity['id'] ?? null)
            ? $subEntity['id']
            : (is_string($payEntity['subscription_id'] ?? null) ? $payEntity['subscription_id'] : null);
        $razorpayPayId = is_string($payEntity['id'] ?? null) ? $payEntity['id'] : null;

        $subscription = $razorpaySubId !== null
            ? Subscription::query()->where('razorpay_subscription_id', $razorpaySubId)->first()
            : null;

        try {
            $signatureValid = $webhookSecret !== ''
                && $signature !== null
                && hash_equals(hash_hmac('sha256', $rawBody, $webhookSecret), $signature);

            if (! $signatureValid) {
                $this->logWebhook([
                    'organization_id' => $subscription?->organization_id,
                    'subscription_id' => $subscription?->id,
                    'event_or_endpoint' => $eventName,
                    'http_status' => 401,
                    'razorpay_subscription_id' => $razorpaySubId,
                    'razorpay_payment_id' => $razorpayPayId,
                    'razorpay_event_id' => null, // Allow future retry with valid signature
                    'request_headers' => $this->sanitizeHeaders($headers),
                    'request_payload' => $payload,
                    'response_payload' => ['message' => 'Invalid webhook signature.'],
                    'signature_valid' => false,
                    'status' => 'failed',
                    'error_message' => 'Invalid X-Razorpay-Signature.',
                    'ip_address' => $ipAddress,
                ]);

                $this->auditService->record(
                    action: 'subscription.webhook_signature_failed',
                    auditable: $subscription,
                    newValues: ['event' => $eventName, 'razorpay_subscription_id' => $razorpaySubId],
                    organizationId: $subscription?->organization_id,
                );

                return [
                    'http_status' => 401,
                    'body' => ['message' => 'Invalid webhook signature.'],
                ];
            }

            if ($eventId !== null && PaymentGatewayLog::query()->where('razorpay_event_id', $eventId)->exists()) {
                return [
                    'http_status' => 200,
                    'body' => ['status' => 'duplicate_ignored'],
                ];
            }

            DB::transaction(function () use ($eventName, $subscription, $subEntity, $payEntity): void {
                if (! $subscription instanceof Subscription) {
                    return;
                }

                $oldStatus = $subscription->status;
                $updates = [];

                match ($eventName) {
                    'subscription.authenticated' => $updates['status'] = $subscription->status === 'active' ? 'active' : 'authenticated',
                    'subscription.activated', 'subscription.charged' => $updates = array_filter([
                        'status' => 'active',
                        'current_period_start' => isset($subEntity['current_start']) ? Carbon::createFromTimestamp((int) $subEntity['current_start']) : $subscription->current_period_start ?? now(),
                        'current_period_end' => isset($subEntity['current_end']) ? Carbon::createFromTimestamp((int) $subEntity['current_end']) : $subscription->current_period_end ?? now()->addMonth(),
                    ]),
                    'subscription.halted', 'payment.failed' => $updates['status'] = 'halted',
                    'subscription.cancelled' => $updates = [
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ],
                    'subscription.completed' => $updates['status'] = 'completed',
                    default => null,
                };

                if ($updates !== []) {
                    $subscription->update($updates);

                    $this->auditService->record(
                        action: "subscription.webhook.{$eventName}",
                        auditable: $subscription,
                        oldValues: ['status' => $oldStatus],
                        newValues: $updates,
                        organizationId: $subscription->organization_id,
                    );
                }

                if ($subscription->organization !== null && in_array($eventName, ['subscription.halted', 'payment.failed', 'subscription.cancelled'], true)) {
                    $this->subscriptionService->notifyOrgAdmins(
                        $subscription->organization,
                        $subscription,
                        $eventName,
                        $eventName === 'subscription.cancelled' ? 'Subscription Cancelled' : 'Subscription Payment Failed',
                        "Webhook update ({$eventName}) received for your subscription. Payment ID: " . ($payEntity['id'] ?? 'N/A'),
                    );
                }
            });

            $this->logWebhook([
                'organization_id' => $subscription?->organization_id,
                'subscription_id' => $subscription?->id,
                'event_or_endpoint' => $eventName,
                'http_status' => 200,
                'razorpay_subscription_id' => $razorpaySubId,
                'razorpay_payment_id' => $razorpayPayId,
                'razorpay_event_id' => $eventId,
                'request_headers' => $this->sanitizeHeaders($headers),
                'request_payload' => $payload,
                'response_payload' => ['status' => 'processed'],
                'signature_valid' => true,
                'status' => 'success',
                'ip_address' => $ipAddress,
            ]);

            return [
                'http_status' => 200,
                'body' => ['status' => 'processed'],
            ];
        } catch (Throwable $exception) {
            $this->logWebhook([
                'organization_id' => $subscription?->organization_id,
                'subscription_id' => $subscription?->id,
                'event_or_endpoint' => $eventName,
                'http_status' => 500,
                'razorpay_subscription_id' => $razorpaySubId,
                'razorpay_payment_id' => $razorpayPayId,
                'razorpay_event_id' => null,
                'request_headers' => $this->sanitizeHeaders($headers),
                'request_payload' => $payload,
                'response_payload' => ['message' => 'Webhook processing exception.'],
                'signature_valid' => $signature !== null ? false : null,
                'status' => 'exception',
                'error_message' => $exception->getMessage(),
                'ip_address' => $ipAddress,
            ]);

            $this->auditService->record(
                action: 'subscription.webhook_exception',
                auditable: $subscription,
                newValues: ['event' => $eventName, 'error' => $exception->getMessage()],
                organizationId: $subscription?->organization_id,
            );

            return [
                'http_status' => 500,
                'body' => ['message' => 'Webhook processing error logged.'],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function logWebhook(array $attributes): void
    {
        try {
            PaymentGatewayLog::query()->create(array_merge($attributes, [
                'direction' => 'inbound_webhook',
                'http_method' => 'POST',
                'created_at' => now(),
            ]));
        } catch (Throwable) {
            // Never let secondary logging failure mask primary exception handling.
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === strtolower($name)) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $allowed = ['content-type', 'user-agent', 'x-razorpay-event-id', 'x-razorpay-signature'];
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, $allowed, true)) {
                $sanitized[$lower] = is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return $sanitized;
    }
}
