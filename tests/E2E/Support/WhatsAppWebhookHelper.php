<?php

namespace Tests\E2E\Support;

final class WhatsAppWebhookHelper
{
    public static function signPayload(string $rawJson, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $rawJson, $secret);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function inboundTextPayload(
        string $phoneNumberId,
        string $fromPhone,
        string $textBody,
        string $wamid = 'wamid.HBgM1001',
        array $overrides = [],
    ): array {
        $waId = ltrim($fromPhone, '+');

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => (string) ($overrides['waba_id'] ?? '100000000000001'),
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => (string) ($overrides['display_phone_number'] ?? '15550100001'),
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => (string) ($overrides['contact_name'] ?? 'Prospect User'),
                            ],
                            'wa_id' => $waId,
                        ]],
                        'messages' => [[
                            'from' => $waId,
                            'id' => $wamid,
                            'timestamp' => (string) ($overrides['timestamp'] ?? time()),
                            'type' => 'text',
                            'text' => [
                                'body' => $textBody,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function coexistenceEchoPayload(
        string $phoneNumberId,
        string $toPhone,
        string $textBody,
        string $wamid = 'wamid.ECHO1001',
        array $overrides = [],
    ): array {
        $businessPhone = ltrim((string) ($overrides['business_phone'] ?? $overrides['display_phone_number'] ?? '15550100001'), '+');
        $displayPhoneNumber = (string) ($overrides['display_phone_number'] ?? $businessPhone);

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => (string) ($overrides['waba_id'] ?? '100000000000001'),
                'changes' => [[
                    'field' => (string) ($overrides['field'] ?? 'smb_message_echoes'),
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => $displayPhoneNumber,
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'message_echoes' => [[
                            'from' => $businessPhone,
                            'to' => ltrim($toPhone, '+'),
                            'id' => $wamid,
                            'timestamp' => (string) ($overrides['timestamp'] ?? time()),
                            'type' => 'text',
                            'text' => [
                                'body' => $textBody,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function statusUpdatePayload(
        string $phoneNumberId,
        string $recipientId,
        string $wamid,
        string $status = 'delivered',
        array $overrides = [],
    ): array {
        $statusEntry = [
            'id' => $wamid,
            'status' => $status,
            'timestamp' => (string) ($overrides['timestamp'] ?? time()),
            'recipient_id' => ltrim($recipientId, '+'),
        ];

        if (! empty($overrides['errors']) && is_array($overrides['errors'])) {
            $statusEntry['errors'] = $overrides['errors'];
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => (string) ($overrides['waba_id'] ?? '100000000000001'),
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => (string) ($overrides['display_phone_number'] ?? '15550100001'),
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'statuses' => [$statusEntry],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
