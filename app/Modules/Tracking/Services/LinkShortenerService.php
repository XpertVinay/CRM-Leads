<?php

namespace App\Modules\Tracking\Services;

use App\Models\Organization;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Events\LinkClickedEvent;
use App\Modules\Tracking\Events\TouchpointCreatedEvent;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinkShortenerService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Organization|int $organization, array $attributes): TrackingLink
    {
        $organizationId = $organization instanceof Organization ? (int) $organization->id : $organization;

        $destinationUrl = trim((string) ($attributes['destination_url'] ?? ''));
        $scheme = strtolower((string) parse_url($destinationUrl, PHP_URL_SCHEME));
        if (! filter_var($destinationUrl, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'destination_url' => ['Destination URL must be a valid http or https URL.'],
            ]);
        }

        $rawCode = isset($attributes['code']) ? trim((string) $attributes['code']) : '';
        $code = $rawCode !== '' ? Str::slug($rawCode) : $this->generateUniqueCode();

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => ['Tracking link code must contain alphanumeric characters.'],
            ]);
        }

        if (TrackingLink::withoutGlobalScope(OrganizationScope::class)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => ['The tracking link code has already been taken.'],
            ]);
        }

        return TrackingLink::create([
            'organization_id' => $organizationId,
            'code' => Str::limit($code, 32, ''),
            'name' => trim((string) $attributes['name']),
            'destination_url' => $destinationUrl,
            'channel' => (string) $attributes['channel'],
            'utm_source' => $this->nullableTrim($attributes['utm_source'] ?? null),
            'utm_medium' => $this->nullableTrim($attributes['utm_medium'] ?? null),
            'utm_campaign' => $this->nullableTrim($attributes['utm_campaign'] ?? null),
            'utm_term' => $this->nullableTrim($attributes['utm_term'] ?? null),
            'utm_content' => $this->nullableTrim($attributes['utm_content'] ?? null),
            'ad_spend' => $attributes['ad_spend'] ?? 0,
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
        ]);
    }

    public function generateUniqueCode(int $length = 8): string
    {
        do {
            $candidate = Str::lower(Str::random($length));
        } while (TrackingLink::withoutGlobalScope(OrganizationScope::class)->where('code', $candidate)->exists());

        return $candidate;
    }

    public function hashIp(string $ip, ?CarbonInterface $date = null): string
    {
        $day = ($date ?? now('UTC'))->toDateString();
        $secret = (string) config('app.key').'|'.$day;

        return hash_hmac('sha256', $ip, $secret);
    }

    public function recordClick(TrackingLink $link, Request $request): LinkClick
    {
        $clickedAt = now();
        $clickToken = (string) Str::uuid();
        $ipHash = $this->hashIp((string) ($request->ip() ?? '0.0.0.0'), $clickedAt->copy()->utc());
        $userAgent = $request->userAgent();
        $referer = $request->headers->get('referer');

        /** @var array{click: LinkClick, touchpoint: Touchpoint} $result */
        $result = DB::transaction(function () use ($link, $clickToken, $ipHash, $userAgent, $referer, $clickedAt): array {
            $click = LinkClick::create([
                'organization_id' => (int) $link->organization_id,
                'tracking_link_id' => (int) $link->id,
                'click_token' => $clickToken,
                'ip_hash' => $ipHash,
                'user_agent' => is_string($userAgent) && $userAgent !== '' ? Str::limit($userAgent, 255, '') : null,
                'referer' => is_string($referer) && $referer !== '' ? Str::limit($referer, 255, '') : null,
                'clicked_at' => $clickedAt,
            ]);

            $touchpoint = Touchpoint::create([
                'organization_id' => (int) $link->organization_id,
                'tracking_link_id' => (int) $link->id,
                'link_click_id' => (int) $click->id,
                'event_milestone' => 'click',
                'occurred_at' => $clickedAt,
                'metadata' => [
                    'channel' => $link->channel,
                    'utm_source' => $link->utm_source,
                    'utm_medium' => $link->utm_medium,
                    'utm_campaign' => $link->utm_campaign,
                ],
                'created_at' => $clickedAt,
            ]);

            return [
                'click' => $click,
                'touchpoint' => $touchpoint,
            ];
        });

        LinkClickedEvent::dispatch(
            (int) $link->organization_id,
            (int) $link->id,
            (int) $result['click']->id,
            $clickToken,
        );

        TouchpointCreatedEvent::dispatch(
            (int) $link->organization_id,
            (int) $result['touchpoint']->id,
            'click',
        );

        return $result['click'];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
