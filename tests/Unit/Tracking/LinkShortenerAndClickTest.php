<?php

namespace Tests\Unit\Tracking;

use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tracking\Events\LinkClickedEvent;
use App\Modules\Tracking\Events\TouchpointCreatedEvent;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Services\LinkShortenerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class LinkShortenerAndClickTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_daily_hmac_ip_hash_is_deterministic_within_day_and_rotates_across_days(): void
    {
        $service = app(LinkShortenerService::class);
        $day1 = CarbonImmutable::parse('2026-05-01 10:00:00', 'UTC');
        $day1Later = CarbonImmutable::parse('2026-05-01 23:59:59', 'UTC');
        $day2 = CarbonImmutable::parse('2026-05-02 00:00:01', 'UTC');

        $hashA1 = $service->hashIp('203.0.113.10', $day1);
        $hashA1Later = $service->hashIp('203.0.113.10', $day1Later);
        $hashA2 = $service->hashIp('203.0.113.10', $day2);
        $hashB1 = $service->hashIp('203.0.113.11', $day1);

        $this->assertSame(64, strlen($hashA1));
        $this->assertSame($hashA1, $hashA1Later);
        $this->assertNotSame($hashA1, $hashA2);
        $this->assertNotSame($hashA1, $hashB1);
    }

    public function test_record_click_logs_click_and_touchpoint_with_zero_false_conversions(): void
    {
        Event::fake([LinkClickedEvent::class, TouchpointCreatedEvent::class]);

        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $service = app(LinkShortenerService::class);
        $link = $service->create($org, [
            'name' => 'Meta Spring Ad',
            'destination_url' => 'https://example.test/promo',
            'channel' => 'ad',
            'utm_source' => 'meta',
            'ad_spend' => 250.00,
        ]);

        $request = Request::create('/l/'.$link->code, 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.42',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 TestBrowser',
            'HTTP_REFERER' => 'https://facebook.com/',
        ]);

        $click = $service->recordClick($link, $request);

        $this->assertTrue(Str::isUuid($click->click_token));
        $this->assertSame((int) $org->id, (int) $click->organization_id);
        $this->assertSame(1, LinkClick::withoutGlobalScopes()->where('tracking_link_id', $link->id)->count());

        $touchpoint = Touchpoint::withoutGlobalScopes()->where('link_click_id', $click->id)->first();
        $this->assertNotNull($touchpoint);
        $this->assertSame('click', $touchpoint->event_milestone);
        $this->assertNull($touchpoint->lead_id);
        $this->assertNull($touchpoint->deal_id);

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
        $this->assertSame(0, Deal::withoutGlobalScopes()->count());

        Event::assertDispatched(LinkClickedEvent::class);
        Event::assertDispatched(TouchpointCreatedEvent::class);
    }

    public function test_create_rejects_invalid_scheme_and_duplicate_code(): void
    {
        $tenant = $this->createOrgWithUser();
        $service = app(LinkShortenerService::class);

        $service->create($tenant['organization'], [
            'name' => 'Valid Link',
            'code' => 'unique-code',
            'destination_url' => 'https://example.test/ok',
            'channel' => 'email',
        ]);

        try {
            $service->create($tenant['organization'], [
                'name' => 'Bad URL',
                'destination_url' => 'javascript:alert(1)',
                'channel' => 'email',
            ]);
            $this->fail('Expected ValidationException for javascript: URL.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('destination_url', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $service->create($tenant['organization'], [
            'name' => 'Dup Code',
            'code' => 'unique-code',
            'destination_url' => 'https://example.test/dup',
            'channel' => 'email',
        ]);
    }
}
