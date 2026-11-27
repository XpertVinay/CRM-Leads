<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tenancy\Services\RbacService;
use Database\Seeders\DevVerificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DevVerificationSeederAndRbacAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_verification_seeder_is_idempotent_and_populates_all_prc_entities(): void
    {
        $this->seed(DevVerificationSeeder::class);
        $this->seed(DevVerificationSeeder::class);

        $this->assertSame(4, Organization::withTrashed()->count());
        $this->assertSame(10, User::query()->count());
        $this->assertSame(5, Lead::withoutGlobalScope(OrganizationScope::class)->count());
        $this->assertSame(5, Deal::withoutGlobalScope(OrganizationScope::class)->count());
        $this->assertDatabaseCount('rbac_roles', 5);
        $this->assertDatabaseCount('whatsapp_accounts', 1);
        $this->assertDatabaseCount('subscriptions', 3);
        $this->assertDatabaseCount('coupons', 3);
        $this->assertDatabaseCount('custom_field_definitions', 7);
        $this->assertDatabaseCount('follow_ups', 4);
        $this->assertDatabaseCount('audit_logs', 2);

        $superadmin = User::query()->whereBlindIndex('email_hash', 'superadmin@crm.local', 'email')->firstOrFail();
        $this->assertTrue($superadmin->isSuperadmin());
    }

    public function test_rbac_audit_fixes_for_pipelines_analytics_reports_and_overrides(): void
    {
        $this->seed(DevVerificationSeeder::class);

        $acme = Organization::query()->where('slug', 'acme-tech')->firstOrFail();
        $rep1 = User::query()->whereBlindIndex('email_hash', 'rep1@acme.local', 'email')->firstOrFail();
        $viewer = User::query()->whereBlindIndex('email_hash', 'viewer@acme.local', 'email')->firstOrFail();
        $support = User::query()->whereBlindIndex('email_hash', 'support@acme.local', 'email')->firstOrFail();

        // 1. pipelines.view allows GET /settings/pipelines and GET /ajax/pipelines, blocks POST
        $this->actingAs($viewer)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/settings/pipelines')
            ->assertOk();
        $this->getJson('/ajax/pipelines')->assertOk();
        $this->postJson('/ajax/pipelines', [
            'name' => 'Blocked Pipeline',
            'stages' => [['name' => 'Open', 'category' => 'open']],
        ])->assertStatus(403);

        // 2. rep1 has allow override for reports.view and deny override for tracking_links.create
        $this->actingAs($rep1)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/reports')
            ->assertOk();
        $this->get('/reports/export')->assertOk();
        $this->postJson('/ajax/tracking-links', [
            'name' => 'Blocked Link',
            'destination_url' => 'https://example.com',
            'channel' => 'ad',
        ])->assertStatus(403);

        // 3. analytics.view and reports.view work independently of campaigns.view and leads.view
        $rbac = app(RbacService::class);
        $leadsView = RbacPermission::query()->where('slug', 'leads.view')->firstOrFail();
        $campaignsView = RbacPermission::query()->where('slug', 'campaigns.view')->firstOrFail();
        $analyticsView = RbacPermission::query()->where('slug', 'analytics.view')->firstOrFail();

        $rbac->setUserOverride($acme, $rep1, $leadsView->id, false);
        $rbac->setUserOverride($acme, $rep1, $campaignsView->id, false);
        $rbac->setUserOverride($acme, $rep1, $analyticsView->id, true);

        $this->actingAs($rep1)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/reports')
            ->assertOk();
        $this->get('/analytics/executive')->assertOk();
        $this->get('/leads')->assertStatus(403);

        // 4. Custom role support_agent gets its assigned permissions only
        $this->actingAs($support)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/whatsapp/chat')
            ->assertOk();
        $this->get('/leads')->assertOk();
        $this->get('/reports')->assertStatus(403);
        $this->get('/settings/team')->assertStatus(403);
    }

    public function test_multi_org_switching_subscription_gate_api_token_and_superadmin_menus_ui(): void
    {
        $this->seed(DevVerificationSeeder::class);

        $acme = Organization::query()->where('slug', 'acme-tech')->firstOrFail();
        $nova = Organization::query()->where('slug', 'nova-retail')->firstOrFail();
        $beta = Organization::query()->where('slug', 'beta-pending')->firstOrFail();

        $superadmin = User::query()->whereBlindIndex('email_hash', 'superadmin@crm.local', 'email')->firstOrFail();
        $admin = User::query()->whereBlindIndex('email_hash', 'admin@acme.local', 'email')->firstOrFail();
        $pending = User::query()->whereBlindIndex('email_hash', 'pending@beta.local', 'email')->firstOrFail();
        $manager = User::query()->whereBlindIndex('email_hash', 'manager@acme.local', 'email')->firstOrFail();

        // Multi-org role switch: admin is org_admin in Acme, read_only in Nova (no WhatsApp)
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/settings/team')
            ->assertOk();

        $this->postJson('/ajax/organizations/switch', ['organization_id' => $nova->id])->assertOk();
        $this->get('/settings/team')->assertStatus(403);
        $this->get('/dashboard')->assertOk();
        $this->get('/leads')->assertOk();

        // Subscription RBAC override consistency on SubscriptionController
        $subPerm = RbacPermission::query()->where('slug', 'subscription.view')->firstOrFail();
        app(RbacService::class)->setUserOverride($acme, $manager, $subPerm->id, true);
        $this->actingAs($manager)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/subscription')
            ->assertOk()
            ->assertSee('Choose or Upgrade Plan', false);

        // Pending payment organization hits subscription gate
        $this->actingAs($pending)
            ->withSession(['active_organization_id' => $beta->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertRedirect(route('subscription.index'));
        $this->getJson('/ajax/leads')->assertStatus(402);
        $this->get('/subscription')->assertOk();

        // Seeded Bearer API Token works against /api/me
        $this->withHeaders(['Authorization' => 'Bearer ' . DevVerificationSeeder::DEV_API_TOKEN])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('organization.slug', 'acme-tech')
            ->assertJsonPath('organization.role', 'org_admin');

        // Superadmin menus page uses valid form attributes and RBAC page renders decrypted sorted users
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $acme->id, 'locale_selected' => true])
            ->get('/superadmin/menus')
            ->assertOk()
            ->assertSee('form="menu-update-', false);

        $this->get('/superadmin/rbac?organization_id=' . $acme->id)
            ->assertOk()
            ->assertSee('Rohan Sales Executive')
            ->assertSee('Ananya Support Agent');

        // Inviter tracking check: admin2 invited rep2, admin invited rep1
        $rep1 = User::query()->whereBlindIndex('email_hash', 'rep1@acme.local', 'email')->firstOrFail();
        $rep2 = User::query()->whereBlindIndex('email_hash', 'rep2@acme.local', 'email')->firstOrFail();
        $this->assertSame(
            $admin->id,
            (int) DB::table('organization_user')->where('organization_id', $acme->id)->where('user_id', $rep1->id)->value('invited_by_user_id')
        );
        $this->assertNotSame(
            $admin->id,
            (int) DB::table('organization_user')->where('organization_id', $acme->id)->where('user_id', $rep2->id)->value('invited_by_user_id')
        );
    }
}
