<?php

namespace App\Modules\Tenancy\Support;

use Illuminate\Support\Str;

class OrganizationInputNormalizer
{
    /**
     * @return array{slug: mixed, invalid_slug: bool}
     */
    public static function normalizeSlug(mixed $rawSlug): array
    {
        if (! is_string($rawSlug)) {
            return ['slug' => $rawSlug, 'invalid_slug' => false];
        }

        $trimmed = trim($rawSlug);
        if ($trimmed === '') {
            return ['slug' => null, 'invalid_slug' => false];
        }

        $normalized = Str::slug($trimmed);
        if ($normalized === '') {
            return ['slug' => $trimmed, 'invalid_slug' => true];
        }

        return ['slug' => $normalized, 'invalid_slug' => false];
    }

    public static function normalizeNullableEmail(mixed $email): mixed
    {
        if (! is_string($email)) {
            return $email;
        }

        $trimmed = trim($email);

        return $trimmed === '' ? null : Str::lower($trimmed);
    }

    public static function normalizeNullableString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
