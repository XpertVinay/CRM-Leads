<?php

namespace App\Http\Middleware;

use App\Support\LocaleHelper;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = (string) config('localization.cookie_name', 'crm_locale');
        $cookieMinutes = (int) config('localization.cookie_minutes', 60 * 24 * 365);

        $queryLocale = LocaleHelper::normalize($request->query('lang'));
        $userLocale = LocaleHelper::normalize($request->user()?->locale);
        $sessionLocale = $request->hasSession()
            ? LocaleHelper::normalize($request->session()->get('locale'))
            : null;
        $cookieLocale = LocaleHelper::normalize($request->cookie($cookieName));

        $locale = $queryLocale
            ?? $userLocale
            ?? $sessionLocale
            ?? $cookieLocale
            ?? (string) config('localization.default', 'en_IN');

        if ($queryLocale !== null) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $queryLocale);
                $request->session()->put('locale_selected', true);
            }
            Cookie::queue($cookieName, $queryLocale, $cookieMinutes);
        }

        App::setLocale($locale);

        $carbonLocale = (string) config("localization.supported.{$locale}.carbon_locale", 'en_IN');
        Carbon::setLocale($carbonLocale);

        return $next($request);
    }
}
