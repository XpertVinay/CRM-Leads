<?php

namespace App\Http\Controllers;

use App\Support\LocaleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $rawLocale = $request->input('locale', config('localization.fallback', 'hi_Latn'));
        $locale = LocaleHelper::normalize(is_string($rawLocale) ? $rawLocale : null);

        if ($locale === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Unsupported language selected.'),
                ], 422);
            }

            return back()->withErrors(['locale' => __('Unsupported language selected.')]);
        }

        $this->persistLocale($request, $locale);

        if ($request->expectsJson()) {
            return response()->json([
                'locale' => $locale,
                'message' => __('Language updated.'),
            ]);
        }

        return back();
    }

    public function switch(Request $request, string $locale): RedirectResponse
    {
        $normalized = LocaleHelper::normalize($locale)
            ?? (string) config('localization.fallback', 'hi_Latn');

        $this->persistLocale($request, $normalized);

        return back();
    }

    private function persistLocale(Request $request, string $locale): void
    {
        App::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
            $request->session()->put('locale_selected', true);
        }

        $cookieName = (string) config('localization.cookie_name', 'crm_locale');
        $cookieMinutes = (int) config('localization.cookie_minutes', 60 * 24 * 365);
        Cookie::queue($cookieName, $locale, $cookieMinutes);

        if ($request->user() && $request->user()->locale !== $locale) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }
    }
}
