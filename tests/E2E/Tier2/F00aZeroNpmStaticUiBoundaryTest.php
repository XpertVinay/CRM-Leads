<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #1: Zero-NPM Static + Livewire 3 UI.
 */
class F00aZeroNpmStaticUiBoundaryTest extends E2ETestCase
{
    public function test_t2_f00a_01_missing_public_build_directory_does_not_break_any_web_or_livewire_page(): void
    {
        $this->assertFalse(
            File::exists(public_path('build/manifest.json')),
            'Shared-server deployment must not require public/build/manifest.json.'
        );

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'No Build Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        foreach (['/dashboard', '/organizations', '/settings/team', '/settings/custom-fields'] as $route) {
            $res = $this->get($route);
            $res->assertStatus(200);
            $this->assertNoViteAssets($res);
            $res->assertDontSee('/build/assets/');
        }
    }

    public function test_t2_f00a_02_unauthenticated_web_and_ajax_requests_fail_or_redirect_safely(): void
    {
        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $webRes = $this->get('/dashboard');
        $webRes->assertStatus(302);
        $webRes->assertRedirect('/login');

        $settingsRes = $this->get('/settings/custom-fields');
        $settingsRes->assertStatus(302);
        $settingsRes->assertRedirect('/login');

        $ajaxListRes = $this->getJson('/ajax/custom-fields?entity_type=lead');
        $ajaxListRes->assertStatus(401);
        $ajaxListRes->assertJsonStructure(['message']);

        $ajaxSwitchRes = $this->postJson('/ajax/organizations/switch', ['organization_id' => 1]);
        $ajaxSwitchRes->assertStatus(401);
        $ajaxSwitchRes->assertJsonStructure(['message']);
    }

    public function test_t2_f00a_03_ajax_routes_return_json_error_payloads_on_validation_and_not_found(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'AJAX Error JSON Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $validationRes = $this->post('/ajax/custom-fields', [], [
            'Accept' => '*/*',
            'X-Organization-ID' => (string) $org->id,
        ]);
        $validationRes->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $validationRes->headers->get('Content-Type'));
        $validationRes->assertJsonStructure(['message', 'errors']);

        $notFoundRes = $this->delete('/ajax/custom-fields/999999', [], [
            'Accept' => '*/*',
            'X-Organization-ID' => (string) $org->id,
        ]);
        $notFoundRes->assertStatus(404);
        $this->assertStringContainsString('application/json', (string) $notFoundRes->headers->get('Content-Type'));
        $notFoundRes->assertJsonStructure(['message']);
    }

    public function test_t2_f00a_04_unauthenticated_or_unauthorized_livewire_actions_are_blocked(): void
    {
        $this->assertTrue($this->hasLivewireInstalled());

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Auth Guard Org']);
        $org = $tenant['organization'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $managerClass = $this->livewireClassIfExists('App\\Modules\\CustomFields\\Livewire\\CustomFieldManager');
        $switcherClass = $this->livewireClassIfExists('App\\Modules\\Tenancy\\Livewire\\OrganizationSwitcher');
        $this->assertNotNull($managerClass);
        $this->assertNotNull($switcherClass);

        $this->actingAsInOrganization($readOnly, $org->id);

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        $readOnlyBlocked = false;
        try {
            \Livewire\Livewire::actingAs($readOnly)
                ->test($managerClass)
                ->set('entityType', 'lead')
                ->set('fieldKey', 'forbidden_lw_field')
                ->set('label', 'Forbidden')
                ->set('dataType', 'text')
                ->call('createField');
        } catch (AuthorizationException|HttpException $e) {
            $readOnlyBlocked = true;
            if ($e instanceof HttpException) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertTrue($readOnlyBlocked, 'Read-only member must be blocked from Livewire CustomFieldManager::createField.');
        $this->assertSame(0, DB::table('custom_field_definitions')->where('organization_id', $org->id)->count());

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $guestBlocked = false;
        try {
            \Livewire\Livewire::test($switcherClass)
                ->call('switchOrganization', (int) $org->id);
        } catch (AuthorizationException|HttpException $e) {
            $guestBlocked = true;
            if ($e instanceof HttpException) {
                $this->assertTrue(in_array($e->getStatusCode(), [401, 403], true));
            }
        }

        $this->assertTrue($guestBlocked, 'Unauthenticated Livewire OrganizationSwitcher action must be blocked.');
    }

    public function test_t2_f00a_05_non_member_livewire_organization_switch_is_forbidden_without_mutating_session(): void
    {
        $this->assertTrue($this->hasLivewireInstalled());

        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'LW NonMember A']);
        $userA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'LW NonMember B'])['organization'];

        $switcherClass = $this->livewireClassIfExists('App\\Modules\\Tenancy\\Livewire\\OrganizationSwitcher');
        $this->assertNotNull($switcherClass);

        $this->actingAsInOrganization($userA, $orgA->id);

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($orgA);
        }

        $blocked = false;
        try {
            \Livewire\Livewire::actingAs($userA)
                ->test($switcherClass)
                ->call('switchOrganization', (int) $orgB->id);
        } catch (AuthorizationException|HttpException $e) {
            $blocked = true;
            if ($e instanceof HttpException) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertTrue($blocked, 'Switching to a non-member organization via Livewire must return 403.');
        $this->assertSame((int) $orgA->id, (int) session('active_organization_id'));
    }
}
