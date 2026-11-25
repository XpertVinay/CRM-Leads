<?php

namespace App\Modules\Tracking\Services;

use App\Models\Organization;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Tenancy\Models\OrganizationSetting;
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

        $rawSlug = isset($attributes['slug']) ? trim((string) $attributes['slug']) : '';
        $slug = $rawSlug !== '' ? Str::limit(Str::slug($rawSlug), 100, '') : null;

        if ($slug !== null && $slug !== '' && TrackingLink::withoutGlobalScope(OrganizationScope::class)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The tracking link slug has already been taken.'],
            ]);
        }

        $destinationUrl = trim((string) ($attributes['destination_url'] ?? ''));
        if ($destinationUrl === '') {
            $destinationUrl = url('/go/'.($slug ?: $code));
        }
        $scheme = strtolower((string) parse_url($destinationUrl, PHP_URL_SCHEME));
        if (! filter_var($destinationUrl, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'destination_url' => ['Destination URL must be a valid http or https URL.'],
            ]);
        }

        $assignedUserId = isset($attributes['assigned_user_id']) && $attributes['assigned_user_id'] !== ''
            ? (int) $attributes['assigned_user_id']
            : null;
        if ($assignedUserId !== null) {
            app(LeadAssignmentService::class)->assertOrganizationMember($organizationId, $assignedUserId);
        }

        $captureFieldIds = $this->sanitizeCaptureCustomFieldIds(
            $organizationId,
            $attributes['capture_custom_field_ids'] ?? null,
        );

        return TrackingLink::create([
            'organization_id' => $organizationId,
            'campaign_id' => $attributes['campaign_id'] ?? null,
            'code' => Str::limit($code, 32, ''),
            'slug' => $slug !== '' ? $slug : null,
            'name' => trim((string) $attributes['name']),
            'destination_url' => $destinationUrl,
            'channel' => (string) $attributes['channel'],
            'source' => $this->nullableTrim($attributes['source'] ?? null),
            'medium' => $this->nullableTrim($attributes['medium'] ?? null),
            'utm_source' => $this->nullableTrim($attributes['utm_source'] ?? null),
            'utm_medium' => $this->nullableTrim($attributes['utm_medium'] ?? null),
            'utm_campaign' => $this->nullableTrim($attributes['utm_campaign'] ?? null),
            'utm_term' => $this->nullableTrim($attributes['utm_term'] ?? null),
            'utm_content' => $this->nullableTrim($attributes['utm_content'] ?? null),
            'ad_spend' => $attributes['ad_spend'] ?? 0,
            'whatsapp_number' => $this->nullableTrim($attributes['whatsapp_number'] ?? null),
            'whatsapp_message' => $this->nullableTrim($attributes['whatsapp_message'] ?? null),
            'capture_custom_field_ids' => $captureFieldIds,
            'assigned_user_id' => $assignedUserId,
            'auto_assign_round_robin' => (bool) ($attributes['auto_assign_round_robin'] ?? false),
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
            'expires_at' => $attributes['expires_at'] ?? null,
        ]);
    }

    /**
     * @return array<int, int>|null
     */
    public function sanitizeCaptureCustomFieldIds(int $organizationId, mixed $rawIds): ?array
    {
        if (! is_array($rawIds) || $rawIds === []) {
            return null;
        }

        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($candidateIds === []) {
            return null;
        }

        $validIds = CustomFieldDefinition::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('entity_type', 'lead')
            ->whereIn('id', $candidateIds)
            ->orderBy('order_index')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $validIds !== [] ? $validIds : null;
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
        $rawIp = (string) ($request->ip() ?? '0.0.0.0');
        $ipHash = $this->hashIp($rawIp, $clickedAt->copy()->utc());
        $userAgent = $request->userAgent();
        $visitorHash = hash_hmac('sha256', $rawIp.'|'.(string) $userAgent, (string) config('app.key'));
        $referer = $request->headers->get('referer');
        $captureIp = OrganizationSetting::forOrganization((int) $link->organization_id)->capture_ip_address;
        [$device, $browser] = $this->parseUserAgent(is_string($userAgent) ? $userAgent : '');

        /** @var array{click: LinkClick, touchpoint: Touchpoint} $result */
        $result = DB::transaction(function () use ($link, $clickToken, $ipHash, $rawIp, $captureIp, $visitorHash, $userAgent, $device, $browser, $referer, $request, $clickedAt): array {
            $click = LinkClick::create([
                'organization_id' => (int) $link->organization_id,
                'tracking_link_id' => (int) $link->id,
                'click_token' => $clickToken,
                'ip_hash' => $ipHash,
                'ip_address' => $captureIp ? Str::limit($rawIp, 45, '') : null,
                'visitor_hash' => $visitorHash,
                'user_agent' => is_string($userAgent) && $userAgent !== '' ? Str::limit($userAgent, 255, '') : null,
                'device' => $device,
                'browser' => $browser,
                'referer' => is_string($referer) && $referer !== '' ? Str::limit($referer, 255, '') : null,
                'landing_page' => Str::limit($request->fullUrl(), 255, ''),
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

    /**
     * @return array{0: string, 1: string}
     */
    private function parseUserAgent(string $ua): array
    {
        $lower = strtolower($ua);
        $device = match (true) {
            str_contains($lower, 'ipad') || str_contains($lower, 'tablet') => 'Tablet',
            str_contains($lower, 'mobile') || str_contains($lower, 'android') || str_contains($lower, 'iphone') => 'Mobile',
            str_contains($lower, 'bot') || str_contains($lower, 'crawl') || str_contains($lower, 'spider') => 'Bot',
            default => 'Desktop',
        };

        $browser = match (true) {
            str_contains($lower, 'whatsapp') => 'WhatsApp',
            str_contains($lower, 'edg') => 'Edge',
            str_contains($lower, 'chrome') && ! str_contains($lower, 'edg') => 'Chrome',
            str_contains($lower, 'safari') && ! str_contains($lower, 'chrome') => 'Safari',
            str_contains($lower, 'firefox') => 'Firefox',
            default => 'Other',
        };

        return [$device, $browser];
    }
}
