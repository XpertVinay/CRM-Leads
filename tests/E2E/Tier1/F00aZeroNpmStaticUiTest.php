<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #1: Zero-NPM Static + Livewire 3 UI.
 */
class F00aZeroNpmStaticUiTest extends E2ETestCase
{
    public function test_t1_f00a_01_package_json_has_zero_runtime_dependencies_and_static_assets_exist(): void
    {
        $packageJsonPath = base_path('package.json');
        $this->assertFileExists($packageJsonPath);

        $pkg = json_decode(File::get($packageJsonPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($pkg);
        $this->assertArrayNotHasKey('dependencies', $pkg, 'Runtime NPM dependencies must remain absent.');
        $this->assertArrayNotHasKey('jquery', $pkg['devDependencies'] ?? []);

        $this->assertFileExists(public_path('assets/css/crm.css'));
        $this->assertFileExists(public_path('assets/js/jquery.min.js'));
        $this->assertFileExists(public_path('assets/js/crm.js'));

        $this->assertGreaterThan(0, File::size(public_path('assets/css/crm.css')));
        $this->assertGreaterThan(0, File::size(public_path('assets/js/jquery.min.js')));
        $this->assertGreaterThan(0, File::size(public_path('assets/js/crm.js')));

        foreach (File::allFiles(resource_path('views')) as $viewFile) {
            $this->assertStringNotContainsString(
                '@vite',
                File::get($viewFile->getPathname()),
                "Unexpected @vite directive in {$viewFile->getRelativePathname()}"
            );
        }
    }

    public function test_t1_f00a_02_public_and_authenticated_blade_pages_render_with_static_assets_and_zero_vite(): void
    {
        foreach (['/', '/login', '/register'] as $publicRoute) {
            $publicRes = $this->get($publicRoute);
            $publicRes->assertStatus(200);
            $this->assertNoViteAssets($publicRes);
        }

        $loginHtml = (string) $this->get('/login')->getContent();
        $this->assertStringContainsString('assets/css/crm.css', $loginHtml);
        $this->assertStringContainsString('assets/js/jquery.min.js', $loginHtml);
        $this->assertStringContainsString('assets/js/crm.js', $loginHtml);

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Static UI Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        foreach (['/dashboard', '/organizations', '/settings/team', '/settings/custom-fields'] as $authRoute) {
            $res = $this->get($authRoute);
            $res->assertStatus(200);
            $this->assertNoViteAssets($res);
            $res->assertSee('assets/css/crm.css');
            $res->assertSee('assets/js/jquery.min.js');
            $res->assertSee('assets/js/crm.js');
        }
    }

    public function test_t1_f00a_03_livewire_3_is_installed_via_composer_and_organization_switcher_works(): void
    {
        $this->assertTrue($this->hasLivewireInstalled(), 'Livewire 3 must be installed via Composer.');

        $composer = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('livewire/livewire', $composer['require'] ?? []);

        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'LW Switch Org A']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'LW Switch Org B',
            'slug' => 'lw-switch-org-b',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ])->assertStatus(302);

        $orgB = $user->organizations()->where('slug', 'lw-switch-org-b')->firstOrFail();

        $switcherClass = $this->livewireClassIfExists('App\\Modules\\Tenancy\\Livewire\\OrganizationSwitcher');
        $this->assertNotNull($switcherClass, 'OrganizationSwitcher Livewire component must exist.');

        $this->actingAsInOrganization($user, $orgA->id);

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($orgA);
        }

        \Livewire\Livewire::actingAs($user)
            ->test($switcherClass)
            ->assertSee($orgA->name)
            ->assertSee($orgB->name)
            ->call('switchOrganization', (int) $orgB->id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame((int) $orgB->id, (int) session('active_organization_id'));
    }

    public function test_t1_f00a_04_livewire_custom_field_manager_creates_and_deletes_field_without_vite(): void
    {
        $this->assertTrue($this->hasLivewireInstalled());

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'LW CF Static Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $managerClass = $this->livewireClassIfExists('App\\Modules\\CustomFields\\Livewire\\CustomFieldManager');
        $this->assertNotNull($managerClass, 'CustomFieldManager Livewire component must exist.');

        $this->actingAsInOrganization($admin, $org->id);

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        \Livewire\Livewire::actingAs($admin)
            ->test($managerClass)
            ->call('setEntityType', 'lead')
            ->set('fieldKey', 'static_ui_note')
            ->set('label', 'Static UI Note')
            ->set('dataType', 'text')
            ->call('createField')
            ->assertHasNoErrors()
            ->assertSee('Static UI Note');

        $definitionId = DB::table('custom_field_definitions')
            ->where('organization_id', $org->id)
            ->where('entity_type', 'lead')
            ->where('field_key', 'static_ui_note')
            ->value('id');

        $this->assertNotNull($definitionId);

        \Livewire\Livewire::actingAs($admin)
            ->test($managerClass)
            ->call('deleteField', (int) $definitionId)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('custom_field_definitions', [
            'id' => $definitionId,
        ]);
    }

    public function test_t1_f00a_05_jquery_ajax_endpoints_and_mvc_views_interoperate_without_spa_bundler(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'AJAX MVC Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $pageRes = $this->get('/settings/custom-fields');
        $pageRes->assertStatus(200);
        $this->assertNoViteAssets($pageRes);

        $createRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'ajax_campaign_tag',
            'label' => 'AJAX Campaign Tag',
            'data_type' => 'text',
        ]);
        $this->assertStatusIn($createRes, [200, 201]);

        $listRes = $this->getJson('/ajax/custom-fields?entity_type=lead');
        $listRes->assertStatus(200);

        $items = $this->extractJsonData($listRes);
        $this->assertContains('ajax_campaign_tag', array_column($items, 'field_key'));

        $switchRes = $this->postJson('/ajax/organizations/switch', [
            'organization_id' => $org->id,
        ]);
        $switchRes->assertStatus(200);
    }
}
