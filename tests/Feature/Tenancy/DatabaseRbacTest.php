<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Models\RbacRole;
use App\Modules\Tenancy\Services\RbacService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class DatabaseRbacTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_non_superadmin_cannot_access_superadmin_rbac_portal(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $this->actingAsOrgMember($admin, $org)
            ->get('/superadmin/rbac')
            ->assertStatus(403);
    }

    public function test_superadmin_can_revoke_role_permission_and_hide_menu_and_block_route(): void
    {
        RbacSeeder::seedDefaults();

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $rep = User::factory()->create();
        $manager = User::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);
        $this->addUserToOrg($org, $manager, TenantRole::SalesManager->value);

        // Initially sales_manager can see Reports in sidebar and access /reports
        $this->actingAsOrgMember($manager, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('reports.index').'"', false);

        $this->actingAsOrgMember($manager, $org)
            ->get('/reports')
            ->assertOk();

        // Superadmin revokes reports.view from sales_manager via portal
        $managerRole = RbacRole::query()->where('slug', TenantRole::SalesManager->value)->firstOrFail();
        $reportsPerm = RbacPermission::query()->where('slug', 'reports.view')->firstOrFail();
        $remainingIds = $managerRole->permissions()
            ->where('rbac_permissions.id', '!=', $reportsPerm->id)
            ->pluck('rbac_permissions.id')
            ->all();

        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->post(route('superadmin.rbac.roles.update', $managerRole), [
                'permission_ids' => $remainingIds,
            ])
            ->assertRedirect(route('superadmin.rbac.index'));

        // Cache is flushed: sales_manager no longer sees Reports in sidebar and gets 403 on /reports
        $this->actingAsOrgMember($manager, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('reports.index').'"', false);

        $this->actingAsOrgMember($manager, $org)
            ->get('/reports')
            ->assertStatus(403);

        // sales_rep does not have reports.view by default and gets 403
        $this->actingAsOrgMember($rep, $org)
            ->get('/reports')
            ->assertStatus(403);
    }

    public function test_superadmin_can_apply_per_user_allow_and_deny_overrides(): void
    {
        RbacSeeder::seedDefaults();

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $rep = User::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);

        $leadsPerm = RbacPermission::query()->where('slug', 'leads.view')->firstOrFail();
        $teamPerm = RbacPermission::query()->where('slug', 'team.manage')->firstOrFail();

        // Deny leads.view and allow team.manage specifically for this sales_rep
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->post(route('superadmin.rbac.user-overrides'), [
                'organization_id' => $org->id,
                'user_id' => $rep->id,
                'overrides' => [
                    $leadsPerm->id => 'deny',
                    $teamPerm->id => 'allow',
                ],
            ])
            ->assertRedirect();

        $this->actingAsOrgMember($rep, $org)
            ->get('/leads')
            ->assertStatus(403);

        $this->actingAsOrgMember($rep, $org)
            ->get('/settings/team')
            ->assertOk();

        $this->actingAsOrgMember($rep, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('<span>Leads</span>', false)
            ->assertSee('href="'.route('settings.team').'"', false);
    }

    public function test_direct_db_permission_change_takes_effect_after_cache_flush(): void
    {
        RbacSeeder::seedDefaults();

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $readOnly = User::factory()->create();
        $this->addUserToOrg($org, $readOnly, TenantRole::ReadOnly->value);

        // Warm cache
        $this->actingAsOrgMember($readOnly, $org)
            ->get('/campaigns')
            ->assertOk();

        // Direct DB edit removing campaigns.view from read_only
        $roleId = RbacRole::query()->where('slug', TenantRole::ReadOnly->value)->value('id');
        $permId = RbacPermission::query()->where('slug', 'campaigns.view')->value('id');

        DB::table('rbac_role_permission')
            ->where('role_id', $roleId)
            ->where('permission_id', $permId)
            ->delete();

        app(RbacService::class)->flushCache();

        $this->actingAsOrgMember($readOnly, $org)
            ->get('/campaigns')
            ->assertStatus(403);
    }

    public function test_superadmin_org_admin_and_employee_hierarchy_hides_menus_and_blocks_restricted_routes(): void
    {
        RbacSeeder::seedDefaults();

        ['organization' => $org, 'user' => $orgAdmin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $manager = User::factory()->create();
        $rep = User::factory()->create();
        $readOnly = User::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->addUserToOrg($org, $manager, TenantRole::SalesManager->value);
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);
        $this->addUserToOrg($org, $readOnly, TenantRole::ReadOnly->value);

        // 1. Superadmin sees superadmin-only menus including Roles & Permissions
        $this->actingAsOrgMember($superadmin, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('superadmin.rbac.index').'"', false)
            ->assertSee('href="'.route('organizations.index').'"', false)
            ->assertSee('href="'.route('coupons.index').'"', false);

        // 2. Org Admin sees org-admin menus (Employees, Org Settings, Custom Fields, Subscription)
        //    but does NOT see Superadmin-only menus
        $this->actingAsOrgMember($orgAdmin, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('settings.team').'"', false)
            ->assertSee('href="'.route('settings.organization').'"', false)
            ->assertSee('href="'.route('settings.custom-fields').'"', false)
            ->assertSee('href="'.route('subscription.index').'"', false)
            ->assertDontSee('href="'.route('superadmin.rbac.index').'"', false)
            ->assertDontSee('href="'.route('organizations.index').'"', false)
            ->assertDontSee('href="'.route('coupons.index').'"', false);

        $this->actingAsOrgMember($orgAdmin, $org)->get('/superadmin/rbac')->assertStatus(403);
        $this->actingAsOrgMember($orgAdmin, $org)->get('/coupons')->assertStatus(403);

        // 3. Employees (sales_manager, sales_rep, read_only) do NOT see org-admin or superadmin menus
        //    and receive 403 when accessing restricted pages directly
        foreach ([$manager, $rep, $readOnly] as $employee) {
            $this->actingAsOrgMember($employee, $org)
                ->get('/dashboard')
                ->assertOk()
                ->assertDontSee('href="'.route('settings.team').'"', false)
                ->assertDontSee('href="'.route('settings.organization').'"', false)
                ->assertDontSee('href="'.route('settings.custom-fields').'"', false)
                ->assertDontSee('href="'.route('superadmin.rbac.index').'"', false)
                ->assertDontSee('href="'.route('organizations.index').'"', false)
                ->assertDontSee('href="'.route('coupons.index').'"', false);

            $this->actingAsOrgMember($employee, $org)->get('/settings/team')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/settings/organization')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/settings/custom-fields')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/whatsapp/settings')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/audit-logs')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/superadmin/rbac')->assertStatus(403);
            $this->actingAsOrgMember($employee, $org)->get('/coupons')->assertStatus(403);
        }
    }

    public function test_portal_menu_permission_and_visibility_changes_forbid_direct_route_access(): void
    {
        RbacSeeder::seedDefaults();

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $rep = User::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);

        // Initially sales_rep can see Campaigns and access /campaigns
        $this->actingAsOrgMember($rep, $org)
            ->get('/campaigns')
            ->assertOk();

        // Superadmin restricts Campaigns menu to team.manage
        $campaignsMenu = \App\Modules\Tenancy\Models\RbacMenu::query()->where('route_name', 'campaigns.index')->firstOrFail();
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->patch(route('superadmin.menus.update', $campaignsMenu), [
                'label' => $campaignsMenu->label,
                'route_name' => $campaignsMenu->route_name,
                'route_pattern' => $campaignsMenu->route_pattern,
                'icon' => $campaignsMenu->icon,
                'permission_slug' => 'team.manage',
                'section' => $campaignsMenu->section,
                'requires_org' => 1,
                'is_active' => 1,
                'sort_order' => $campaignsMenu->sort_order,
            ])
            ->assertRedirect(route('superadmin.menus.index'));

        // Hidden in sidebar AND forbidden when visited directly
        $this->actingAsOrgMember($rep, $org)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('<span>Campaigns</span>', false);
        $this->actingAsOrgMember($rep, $org)
            ->get('/campaigns')
            ->assertStatus(403);

        // Superadmin disables menu -> hidden and forbidden even for allowed users
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->patch(route('superadmin.menus.update', $campaignsMenu), [
                'label' => $campaignsMenu->label,
                'route_name' => $campaignsMenu->route_name,
                'route_pattern' => $campaignsMenu->route_pattern,
                'icon' => $campaignsMenu->icon,
                'permission_slug' => 'campaigns.view',
                'section' => $campaignsMenu->section,
                'requires_org' => 1,
                'is_active' => 0,
                'sort_order' => $campaignsMenu->sort_order,
            ])
            ->assertRedirect(route('superadmin.menus.index'));

        $this->actingAsOrgMember($rep, $org)
            ->get('/campaigns')
            ->assertStatus(403);
    }
}
