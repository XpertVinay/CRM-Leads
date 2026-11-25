<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class LocaleHelper
{
    /**
     * Normalize a raw locale string (e.g. "hi_latn", "en-in") to a supported key.
     */
    public static function normalize(?string $locale): ?string
    {
        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        $clean = str_replace('-', '_', trim($locale));
        $supported = array_keys(config('localization.supported', []));

        foreach ($supported as $code) {
            if (strcasecmp($clean, $code) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Determine whether the first-visit language selection popup should be displayed.
     */
    public static function shouldShowSelectionPopup(Request $request): bool
    {
        $cookieName = (string) config('localization.cookie_name', 'crm_locale');

        if ($request->hasSession() && $request->session()->get('locale_selected', false)) {
            return false;
        }

        if ($request->cookie($cookieName)) {
            return false;
        }

        if ($request->user() && self::normalize($request->user()->locale) !== null) {
            return false;
        }

        return true;
    }

    /**
     * Format a numeric amount using the Indian numbering system (1,25,000.00).
     */
    public static function formatInr(float|int|string|null $amount, bool $withSymbol = true, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return $withSymbol ? '₹0.00' : '0.00';
        }

        $value = (float) $amount;
        $negative = $value < 0;
        $abs = abs($value);

        $formatted = number_format($abs, $decimals, '.', '');
        [$intPart, $decPart] = array_pad(explode('.', $formatted), 2, '');

        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $rest = substr($intPart, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $intPart = $rest.','.$lastThree;
        }

        $result = $decimals > 0 ? $intPart.'.'.$decPart : $intPart;
        $prefix = ($negative ? '-' : '').($withSymbol ? '₹' : '');

        return $prefix.$result;
    }

    /**
     * Format a date/datetime in Indian standard dd/mm/yyyy format.
     */
    public static function formatDate(?CarbonInterface $date, string $format = 'd/m/Y'): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->format($format);
    }

    /**
     * Return the JS translation dictionary for the active locale.
     *
     * @return array<string, string>
     */
    public static function jsTranslations(?string $locale = null): array
    {
        $locale = self::normalize($locale ?? app()->getLocale())
            ?? config('localization.default', 'en_IN');

        $path = lang_path($locale.'.json');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
