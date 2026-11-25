<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #9: Short Tracking Links & Public Redirect.
 */
class F09TrackingLinksBoundaryTest extends E2ETestCase
{
    public function test_t2_f09_01_unknown_or_inactive_tracking_link_returns_404_with_zero_clicks(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Inactive Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/tracking-links', [
                'name' => 'Paused Campaign',
                'code' => 'paused-link',
                'destination_url' => 'https://example.test/paused',
                'channel' => 'ad',
                'is_active' => false,
            ])->assertStatus(201);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->get('/l/nonexistent-slug-999')->assertStatus(404);
        $this->get('/l/paused-link')->assertStatus(404);

        $this->assertSame(0, DB::table('link_clicks')->count());
        $this->assertSame(0, DB::table('touchpoints')->count());
    }

    public function test_t2_f09_02_public_redirect_enforces_120_per_minute_rate_limit(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking RateLimit Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/tracking-links', [
                'name' => 'Rate Limit Target',
                'code' => 'rate-limit-120',
                'destination_url' => 'https://example.test/fast',
                'channel' => 'organic',
            ])->assertStatus(201);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        for ($i = 1; $i <= 120; $i++) {
            $this->get('/l/rate-limit-120')->assertStatus(302);
        }

        $limited = $this->get('/l/rate-limit-120');
        $limited->assertStatus(429);

        $this->assertSame(120, DB::table('link_clicks')->count());
    }

    public function test_t2_f09_03_rejects_duplicate_code_invalid_url_invalid_channel_and_negative_ad_spend(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Validation Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/tracking-links', [
            'name' => 'Original Link',
            'code' => 'unique-slug',
            'destination_url' => 'https://example.test/valid',
            'channel' => 'ad',
        ])->assertStatus(201);

        // Duplicate code
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Duplicate Code',
            'code' => 'unique-slug',
            'destination_url' => 'https://example.test/other',
            'channel' => 'ad',
        ])->assertStatus(422);

        // Invalid URL
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Bad URL',
            'code' => 'bad-url-1',
            'destination_url' => 'not-a-valid-url',
            'channel' => 'ad',
        ])->assertStatus(422);

        // Invalid channel
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Bad Channel',
            'code' => 'bad-channel-1',
            'destination_url' => 'https://example.test/valid',
            'channel' => 'carrier_pigeon',
        ])->assertStatus(422);

        // Negative ad_spend
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Negative Spend',
            'code' => 'neg-spend-1',
            'destination_url' => 'https://example.test/valid',
            'channel' => 'ad',
            'ad_spend' => -25.00,
        ])->assertStatus(422);

        // Invalid code format
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Bad Code Format',
            'code' => 'bad slug with spaces!',
            'destination_url' => 'https://example.test/valid',
            'channel' => 'ad',
        ])->assertStatus(422);

        $this->assertSame(1, DB::table('tracking_links')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f09_04_read_only_and_sales_rep_permissions_enforced_on_tracking_links(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking RBAC Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $salesRep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Admin Link',
                'code' => 'admin-link-1',
                'destination_url' => 'https://example.test/a',
                'channel' => 'email',
            ])->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($readOnly, $org->id);
        $this->get('/tracking-links')->assertStatus(200);
        $this->getJson('/ajax/tracking-links')->assertStatus(200);
        $this->postJson('/ajax/tracking-links', [
            'name' => 'RO Attempt',
            'destination_url' => 'https://example.test/ro',
            'channel' => 'ad',
        ])->assertStatus(403);
        $this->putJson("/ajax/tracking-links/{$linkId}", ['name' => 'RO Edit'])->assertStatus(403);
        $this->deleteJson("/ajax/tracking-links/{$linkId}")->assertStatus(403);

        $this->actingAsInOrganization($salesRep, $org->id)
            ->deleteJson("/ajax/tracking-links/{$linkId}")
            ->assertStatus(403);

        $this->assertTrue(DB::table('tracking_links')->where('id', $linkId)->exists());
    }

    public function test_t2_f09_05_cross_organization_tracking_link_update_and_delete_are_blocked(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Tracking Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $linkBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Org B Secret Link',
                'code' => 'org-b-secret',
                'destination_url' => 'https://orgb.example.test',
                'channel' => 'social',
            ])->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($adminA, $orgA->id);

        $updateRes = $this->putJson("/ajax/tracking-links/{$linkBId}", [
            'name' => 'Hijacked Link',
            'destination_url' => 'https://evil.test',
            'channel' => 'ad',
        ]);
        $this->assertStatusIn($updateRes, [403, 404]);

        $deleteRes = $this->deleteJson("/ajax/tracking-links/{$linkBId}");
        $this->assertStatusIn($deleteRes, [403, 404]);

        $this->assertSame('Org B Secret Link', DB::table('tracking_links')->where('id', $linkBId)->value('name'));
    }
}
