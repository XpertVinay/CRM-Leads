<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #9: Short Tracking Links & Public Redirect.
 */
class F09TrackingLinksTest extends E2ETestCase
{
    public function test_t1_f09_01_creates_tracking_link_with_auto_generated_code_and_utm_metadata(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Auto Code Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $response = $this->postJson('/ajax/tracking-links', [
            'name' => 'Spring Search Campaign',
            'destination_url' => 'https://example.test/spring-offer',
            'channel' => 'ad',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_2026',
            'utm_term' => 'crm',
            'utm_content' => 'hero_a',
            'ad_spend' => 500.50,
        ]);

        $this->assertStatusIn($response, [200, 201]);
        $created = $this->extractJsonData($response);

        $this->assertNotEmpty($created['code'] ?? null, 'Tracking link code must be auto-generated when omitted.');
        $this->assertSame((int) $org->id, (int) ($created['organization_id'] ?? 0));

        $row = DB::table('tracking_links')->where('id', $created['id'])->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $org->id, (int) $row->organization_id);
        $this->assertSame('ad', $row->channel);
        $this->assertSame('google', $row->utm_source);
        $this->assertEquals(500.50, (float) $row->ad_spend);
        $this->assertTrue((bool) $row->is_active);

        $page = $this->get('/tracking-links');
        $page->assertStatus(200);
        $this->assertNoViteAssets($page);
        $page->assertSee('Spring Search Campaign');
    }

    public function test_t1_f09_02_lists_updates_and_deletes_tracking_link(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking CRUD Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $createRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'Newsletter Link',
            'code' => 'summer-deal',
            'destination_url' => 'https://example.test/summer',
            'channel' => 'email',
            'ad_spend' => 100.00,
        ]);
        $this->assertStatusIn($createRes, [200, 201]);
        $linkId = (int) $this->extractJsonData($createRes)['id'];

        $listRes = $this->getJson('/ajax/tracking-links');
        $listRes->assertStatus(200);
        $items = $this->extractJsonData($listRes);
        $this->assertContains('summer-deal', array_column($items, 'code'));

        $updateRes = $this->putJson("/ajax/tracking-links/{$linkId}", [
            'name' => 'Newsletter Link Updated',
            'destination_url' => 'https://example.test/summer-v2',
            'channel' => 'email',
            'ad_spend' => 750.00,
            'is_active' => false,
        ]);
        $updateRes->assertStatus(200);

        $updated = DB::table('tracking_links')->where('id', $linkId)->first();
        $this->assertSame('Newsletter Link Updated', $updated->name);
        $this->assertEquals(750.00, (float) $updated->ad_spend);
        $this->assertFalse((bool) $updated->is_active);

        $deleteRes = $this->deleteJson("/ajax/tracking-links/{$linkId}");
        $this->assertStatusIn($deleteRes, [200, 204]);
        $this->assertNull(DB::table('tracking_links')->where('id', $linkId)->first());
    }

    public function test_t1_f09_03_public_redirect_logs_click_daily_hmac_ip_hash_cookie_and_zero_conversions(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Redirect Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $createRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'Black Friday Social',
            'code' => 'black-friday',
            'destination_url' => 'https://promo.example.test/offer',
            'channel' => 'social',
        ]);
        $this->assertStatusIn($createRes, [200, 201]);
        $linkId = (int) $this->extractJsonData($createRes)['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $redirectRes = $this->withHeaders([
            'User-Agent' => 'E2E-Browser/1.0',
            'Referer' => 'https://social.example.test/feed',
        ])->get('/l/black-friday');

        $redirectRes->assertStatus(302);
        $redirectRes->assertRedirect('https://promo.example.test/offer');
        $redirectRes->assertCookie('click_token');

        $clickRow = DB::table('link_clicks')->where('tracking_link_id', $linkId)->first();
        $this->assertNotNull($clickRow);
        $this->assertTrue(Str::isUuid((string) $clickRow->click_token));
        $this->assertSame(64, strlen((string) $clickRow->ip_hash));
        $this->assertTrue(ctype_xdigit((string) $clickRow->ip_hash));
        $this->assertNotSame('127.0.0.1', (string) $clickRow->ip_hash);

        $touchpoint = DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkId)
            ->where('link_click_id', $clickRow->id)
            ->first();
        $this->assertNotNull($touchpoint);
        $this->assertSame('click', $touchpoint->event_milestone);

        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
    }

    public function test_t1_f09_04_multiple_visits_log_distinct_click_tokens_without_creating_leads(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Multi Click Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $createRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'QR Poster',
            'code' => 'qr-poster',
            'destination_url' => 'https://example.test/qr',
            'channel' => 'qr',
        ]);
        $this->assertStatusIn($createRes, [200, 201]);
        $linkId = (int) $this->extractJsonData($createRes)['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        for ($i = 0; $i < 5; $i++) {
            $this->get('/l/qr-poster')->assertStatus(302);
        }

        $clicks = DB::table('link_clicks')->where('tracking_link_id', $linkId)->get();
        $this->assertCount(5, $clicks);
        $this->assertCount(5, $clicks->pluck('click_token')->unique());

        $this->assertSame(
            5,
            DB::table('touchpoints')
                ->where('organization_id', $org->id)
                ->where('tracking_link_id', $linkId)
                ->where('event_milestone', 'click')
                ->count()
        );
        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
    }

    public function test_t1_f09_05_sales_rep_creates_whatsapp_link_and_livewire_manager_renders_when_available(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Rep Org']);
        $org = $tenant['organization'];
        $salesRep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($salesRep, $org->id);

        $createRes = $this->postJson('/ajax/tracking-links', [
            'name' => 'WA Click to Chat',
            'code' => 'wa-chat-1',
            'destination_url' => 'https://wa.me/15551234567?text=Hello',
            'channel' => 'whatsapp',
        ]);
        $this->assertStatusIn($createRes, [200, 201]);

        $managerClass = $this->livewireClassIfExists('App\\Modules\\Tracking\\Livewire\\TrackingLinkManager');
        if ($managerClass !== null) {
            $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
            if (class_exists($tenantContextClass)) {
                $this->app->make($tenantContextClass)->setTenant($org);
            }

            \Livewire\Livewire::actingAs($salesRep)
                ->test($managerClass)
                ->assertSee('WA Click to Chat');
        }
    }
}
