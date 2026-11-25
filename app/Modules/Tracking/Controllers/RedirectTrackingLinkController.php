<?php

namespace App\Modules\Tracking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Models\TrackingLink;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectTrackingLinkController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        LinkShortenerService $shortener,
    ): RedirectResponse {
        $trackingLink = TrackingLink::withoutGlobalScope(OrganizationScope::class)
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $click = $shortener->recordClick($trackingLink, $request);

        return redirect()
            ->away($trackingLink->destination_url, 302)
            ->withCookie(cookie(
                name: 'click_token',
                value: $click->click_token,
                minutes: 60 * 24 * 30,
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
    }
}
