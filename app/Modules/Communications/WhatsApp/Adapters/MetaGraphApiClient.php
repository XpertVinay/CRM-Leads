<?php

namespace App\Modules\Communications\WhatsApp\Adapters;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MetaGraphApiClient
{
    private function baseUrl(): string
    {
        $base = rtrim((string) config('services.meta.graph_base_url', 'https://graph.facebook.com'), '/');
        if (! app()->environment('testing') && ! str_starts_with(strtolower($base), 'https://')) {
            throw new InvalidArgumentException('Meta Graph API base URL must use HTTPS.');
        }
        $version = trim((string) config('services.meta.graph_version', 'v21.0'), '/');

        return "{$base}/{$version}";
    }

    private function http(?string $accessToken = null): PendingRequest
    {
        $request = Http::connectTimeout(5)
            ->timeout(15)
            ->withOptions([
                'verify' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ]);

        return $accessToken !== null ? $request->withToken($accessToken) : $request;
    }

    private function sanitizeNumericId(string $id, string $label): string
    {
        $trimmed = trim($id);
        if ($trimmed === '' || preg_match('/^[A-Za-z0-9_-]+$/', $trimmed) !== 1) {
            throw new InvalidArgumentException("Invalid Meta {$label}.");
        }

        return $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = $this->http()->get($this->baseUrl() . '/oauth/access_token', [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'code' => $code,
        ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPhoneNumberProfile(string $phoneNumberId, string $accessToken): array
    {
        $safeId = $this->sanitizeNumericId($phoneNumberId, 'phone_number_id');
        $response = $this->http($accessToken)
            ->get($this->baseUrl() . "/{$safeId}", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating',
            ]);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscribeAppToWaba(string $wabaId, string $accessToken): array
    {
        $safeId = $this->sanitizeNumericId($wabaId, 'waba_id');
        $response = $this->http($accessToken)
            ->post($this->baseUrl() . "/{$safeId}/subscribed_apps");

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTemplates(string $wabaId, string $accessToken): array
    {
        $safeId = $this->sanitizeNumericId($wabaId, 'waba_id');
        $response = $this->http($accessToken)
            ->get($this->baseUrl() . "/{$safeId}/message_templates", [
                'limit' => 100,
            ]);

        $response->throw();

        return $response->json('data') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendTextMessage(
        string $phoneNumberId,
        string $accessToken,
        string $toPhone,
        string $body,
    ): array {
        $safeId = $this->sanitizeNumericId($phoneNumberId, 'phone_number_id');
        $response = $this->http($accessToken)
            ->post($this->baseUrl() . "/{$safeId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($toPhone, '+'),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<int, string>  $parameters
     * @return array<string, mixed>
     */
    public function sendTemplateMessage(
        string $phoneNumberId,
        string $accessToken,
        string $toPhone,
        string $templateName,
        string $languageCode = 'en_US',
        array $parameters = [],
    ): array {
        $safeId = $this->sanitizeNumericId($phoneNumberId, 'phone_number_id');
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($toPhone, '+'),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if ($parameters !== []) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        static fn(string $param): array => [
                            'type' => 'text',
                            'text' => $param,
                        ],
                        $parameters
                    ),
                ]
            ];
        }

        /** @var Response $response */
        $response = $this->http($accessToken)
            ->post($this->baseUrl() . "/{$safeId}/messages", $payload);

        $response->throw();

        return $response->json() ?? [];
    }
}
