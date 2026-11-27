<?php

namespace App\Support\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

class DataEncrypter
{
    private Encrypter $primaryEncrypter;

    private Encrypter $encrypterWithPrevious;

    private string $blindIndexKey;

    private string $cipher;

    public function __construct(?string $key = null, array $previousKeys = [], ?string $blindIndexKey = null, ?string $cipher = null)
    {
        $this->cipher = $cipher ?: (string) config('security.cipher', config('app.cipher', 'aes-256-cbc'));

        $rawKey = $key ?? (string) (config('security.data_encryption_key') ?: config('app.key'));
        if ($rawKey === '') {
            throw new RuntimeException('No DATA_ENCRYPTION_KEY or APP_KEY configured for data encryption.');
        }

        $parsedPrimary = $this->parseKey($rawKey);
        $this->primaryEncrypter = new Encrypter($parsedPrimary, $this->cipher);

        $rawPrev = $previousKeys ?: (array) config('security.data_encryption_previous_keys', []);
        if (empty($rawPrev) && !config('security.data_encryption_key')) {
            $rawPrev = (array) config('app.previous_keys', []);
        }

        $parsedPrevious = array_map(fn (string $k) => $this->parseKey($k), array_filter($rawPrev));

        $this->encrypterWithPrevious = (new Encrypter($parsedPrimary, $this->cipher))
            ->previousKeys($parsedPrevious);

        $rawBlindKey = $blindIndexKey ?? (string) config('security.blind_index_key', '');
        $this->blindIndexKey = $rawBlindKey !== ''
            ? $this->parseKey($rawBlindKey, allowArbitraryLength: true)
            : hash('sha256', 'crm-blind-index:' . $parsedPrimary, true);
    }

    public static function instance(): self
    {
        return app(self::class);
    }

    public function encryptString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->primaryEncrypter->encryptString($value);
    }

    public function decryptString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (!$this->isEncryptedPayload($value)) {
            return $value;
        }

        try {
            return $this->encrypterWithPrevious->decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function encryptArray(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->encryptString($json);
    }

    public function decryptArray(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $plain = $this->decryptString($value);
        if ($plain === null || $plain === '') {
            return null;
        }

        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function blindIndex(?string $value, string $type = 'default'): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeForBlindIndex($value, $type);
        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', "{$type}:{$normalized}", $this->blindIndexKey);
    }

    public function isEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false || $decoded === '') {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json)
            && isset($json['iv'], $json['value'])
            && is_string($json['iv'])
            && is_string($json['value'])
            && (isset($json['mac']) || isset($json['tag']));
    }

    public function needsReEncryption(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (!$this->isEncryptedPayload($value)) {
            return true;
        }

        try {
            $this->primaryEncrypter->decryptString($value);

            return false;
        } catch (DecryptException) {
            try {
                $this->encrypterWithPrevious->decryptString($value);

                return true;
            } catch (DecryptException) {
                return false;
            }
        }
    }

    public function normalizeForBlindIndex(string $value, string $type): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return match ($type) {
            'email' => mb_strtolower($trimmed, 'UTF-8'),
            'phone' => $this->normalizePhone($trimmed),
            'token' => $trimmed,
            default => mb_strtolower($trimmed, 'UTF-8'),
        };
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : mb_strtolower(trim($phone), 'UTF-8');
    }

    private function parseKey(string $key, bool $allowArbitraryLength = false): string
    {
        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(Str::after($key, 'base64:'), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 encryption key.');
            }

            return $decoded;
        }

        if (!$allowArbitraryLength && strlen($key) !== 32) {
            return hash('sha256', $key, true);
        }

        return $key;
    }
}
