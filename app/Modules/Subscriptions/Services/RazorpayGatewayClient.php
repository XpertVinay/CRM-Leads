<?php

namespace App\Modules\Subscriptions\Services;

use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class RazorpayGatewayClient
{
    /**
     * @return array<string, mixed>
     */
    public function createPlan(SubscriptionPlan $plan, ?int $organizationId = null, ?int $userId = null): array
    {
        $payload = [
            'period' => 'monthly',
            'interval' => 1,
            'item' => [
                'name' => $plan->name,
                'amount' => (int) $plan->price_paise,
                'currency' => $plan->currency ?: 'INR',
                'description' => $plan->description ?: $plan->name,
            ],
            'notes' => [
                'plan_code' => $plan->code,
            ],
        ];

        return $this->send(
            method: 'POST',
            endpoint: '/plans',
            payload: $payload,
            organizationId: $organizationId,
            subscriptionId: null,
            userId: $userId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubscription(Subscription $subscription, array $payload, ?int $userId = null): array
    {
        return $this->send(
            method: 'POST',
            endpoint: '/subscriptions',
            payload: $payload,
            organizationId: $subscription->organization_id,
            subscriptionId: $subscription->id,
            userId: $userId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscription(Subscription $subscription, bool $cancelAtCycleEnd = true, ?int $userId = null): array
    {
        $razorpaySubId = (string) $subscription->razorpay_subscription_id;

        return $this->send(
            method: 'POST',
            endpoint: "/subscriptions/{$razorpaySubId}/cancel",
            payload: [
                'cancel_at_cycle_end' => $cancelAtCycleEnd ? 1 : 0,
            ],
            organizationId: $subscription->organization_id,
            subscriptionId: $subscription->id,
            userId: $userId,
            razorpaySubscriptionId: $razorpaySubId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $endpoint,
        array $payload,
        ?int $organizationId,
        ?int $subscriptionId,
        ?int $userId,
        ?string $razorpaySubscriptionId = null,
    ): array {
        $keyId = (string) config('services.razorpay.key_id');
        $keySecret = (string) config('services.razorpay.key_secret');
        $baseUrl = rtrim((string) config('services.razorpay.base_url', 'https://api.razorpay.com/v1'), '/');
        if (! app()->environment('testing') && ! str_starts_with(strtolower($baseUrl), 'https://')) {
            throw new RuntimeException('Razorpay API base URL must use HTTPS.');
        }

        $startedAt = microtime(true);
        $httpStatus = null;
        $responseBody = null;

        try {
            if ($keyId === '' || $keySecret === '') {
                throw new RuntimeException('Razorpay API credentials are not configured.');
            }

            /** @var Response $response */
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->baseUrl($baseUrl)
                ->withOptions([
                    'verify' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                ])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->send($method, $endpoint, ['json' => $payload]);

            $httpStatus = $response->status();
            $decoded = $response->json();
            $responseBody = is_array($decoded) ? $decoded : ['raw' => $response->body()];
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->failed()) {
                $errorMessage = (string) ($responseBody['error']['description'] ?? $responseBody['message'] ?? "Razorpay HTTP {$httpStatus}");

                $this->recordLog([
                    'organization_id' => $organizationId,
                    'subscription_id' => $subscriptionId,
                    'user_id' => $userId,
                    'direction' => 'outbound_api',
                    'event_or_endpoint' => "{$method} {$endpoint}",
                    'http_method' => $method,
                    'http_status' => $httpStatus,
                    'razorpay_subscription_id' => $razorpaySubscriptionId ?? ($responseBody['id'] ?? null),
                    'request_headers' => ['Accept' => 'application/json', 'Authorization' => '[REDACTED]'],
                    'request_payload' => $payload,
                    'response_payload' => $responseBody,
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'duration_ms' => $durationMs,
                ]);

                throw new RuntimeException($errorMessage);
            }

            $this->recordLog([
                'organization_id' => $organizationId,
                'subscription_id' => $subscriptionId,
                'user_id' => $userId,
                'direction' => 'outbound_api',
                'event_or_endpoint' => "{$method} {$endpoint}",
                'http_method' => $method,
                'http_status' => $httpStatus,
                'razorpay_subscription_id' => $razorpaySubscriptionId ?? (is_string($responseBody['id'] ?? null) && str_starts_with($responseBody['id'], 'sub_') ? $responseBody['id'] : null),
                'request_headers' => ['Accept' => 'application/json', 'Authorization' => '[REDACTED]'],
                'request_payload' => $payload,
                'response_payload' => $responseBody,
                'status' => 'success',
                'duration_ms' => $durationMs,
            ]);

            return $responseBody;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // Ensure exceptions (network/timeouts/missing credentials) are always audited if not already logged.
            if ($httpStatus === null || $httpStatus < 400) {
                $this->recordLog([
                    'organization_id' => $organizationId,
                    'subscription_id' => $subscriptionId,
                    'user_id' => $userId,
                    'direction' => 'outbound_api',
                    'event_or_endpoint' => "{$method} {$endpoint}",
                    'http_method' => $method,
                    'http_status' => $httpStatus,
                    'razorpay_subscription_id' => $razorpaySubscriptionId,
                    'request_headers' => ['Accept' => 'application/json', 'Authorization' => '[REDACTED]'],
                    'request_payload' => $payload,
                    'response_payload' => $responseBody,
                    'status' => 'exception',
                    'error_message' => $exception->getMessage(),
                    'duration_ms' => $durationMs,
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordLog(array $attributes): void
    {
        PaymentGatewayLog::query()->create(array_merge($attributes, [
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]));
    }
}
