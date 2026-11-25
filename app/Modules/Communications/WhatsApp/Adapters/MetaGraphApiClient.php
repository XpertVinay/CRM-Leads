<?php

namespace App\Modules\Communications\WhatsApp\Adapters;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaGraphApiClient
{
    private function baseUrl(): string
    {
        $base = rtrim((string) config('services.meta.graph_base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('services.meta.graph_version', 'v21.0'), '/');

        return "{$base}/{$version}";
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get($this->baseUrl().'/oauth/access_token', [
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
        $response = Http::withToken($accessToken)
            ->get($this->baseUrl()."/{$phoneNumberId}", [
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
        $response = Http::withToken($accessToken)
            ->post($this->baseUrl()."/{$wabaId}/subscribed_apps");

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
        $response = Http::withToken($accessToken)
            ->get($this->baseUrl()."/{$wabaId}/message_templates", [
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
        $response = Http::withToken($accessToken)
            ->post($this->baseUrl()."/{$phoneNumberId}/messages", [
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
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $param): array => [
                        'type' => 'text',
                        'text' => $param,
                    ],
                    $parameters
                ),
            ]];
        }

        /** @var Response $response */
        $response = Http::withToken($accessToken)
            ->post($this->baseUrl()."/{$phoneNumberId}/messages", $payload);

        $response->throw();

        return $response->json() ?? [];
    }
}
