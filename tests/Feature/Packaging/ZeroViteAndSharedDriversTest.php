<?php

namespace Tests\Feature\Packaging;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\SharedDriverTestJob;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class ZeroViteAndSharedDriversTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_zero_vite_calls_static_assets_present_and_pages_render_without_npm(): void
    {
        // Zero @vite in any Blade view
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertStringNotContainsString(
                '@vite',
                File::get($file->getPathname()),
                "Unexpected @vite directive found in {$file->getRelativePathname()}",
            );
        }

        // package.json has zero runtime dependencies added
        $pkg = json_decode(File::get(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('dependencies', $pkg);
        $this->assertArrayNotHasKey('jquery', $pkg['devDependencies'] ?? []);

        // Static shared-server assets exist & crm.js has no duplicate org-switcher-select listener
        $this->assertFileExists(public_path('assets/css/crm.css'));
        $this->assertFileExists(public_path('assets/js/jquery.min.js'));
        $this->assertFileExists(public_path('assets/js/crm.js'));
        $this->assertStringNotContainsString(
            '#org-switcher-select',
            File::get(public_path('assets/js/crm.js')),
        );

        // Pages render HTTP 200 without build/manifest.json
        $this->get('/')->assertOk()->assertDontSee('/build/assets/');
        $this->get('/login')->assertOk()->assertSee('assets/css/crm.css');
        $this->get('/register')
            ->assertOk()
            ->assertSee('assets/js/jquery.min.js')
            ->assertSee('name="organization_slug"', false)
            ->assertSee('name="registration_identifier"', false)
            ->assertSee('name="primary_contact_email"', false)
            ->assertSee('name="timezone"', false)
            ->assertSee('name="currency"', false);

        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->actingAsOrgMember($admin, $org);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($org->name)
            ->assertSee('wire:change="switchOrganization', false);
        $this->get('/organizations')->assertOk();
        $this->get('/settings/team')->assertOk();
        $this->get('/settings/custom-fields')->assertOk();
    }

    public function test_explicit_database_cache_store_hydrates_custom_fields_without_incomplete_class(): void
    {
        Config::set('cache.default', 'database');
        Cache::forgetDriver('database');

        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'Healthcare'],
            'is_required' => true,
        ]);

        $registry = app(CustomFieldRegistry::class);
        $registry->forget($org->id, 'lead');

        // First call populates database cache; second call reads & hydrates from database cache
        $first = $registry->forEntity('lead', $org->id);
        $second = $registry->forEntity('lead', $org->id);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertInstanceOf(CustomFieldDefinition::class, $second->first());
        $this->assertSame('industry', $second->first()->field_key);
        $this->assertSame(['Fintech', 'Healthcare'], $second->first()->options);
        $this->assertGreaterThanOrEqual(1, DB::table('cache')->count());

        // RateLimiter also works against database cache store
        $limiter = app(RateLimiter::class);
        $this->assertTrue($limiter->attempt('test-db-rate-limit', 2, static fn (): bool => true));
    }

    public function test_explicit_database_session_driver_persists_active_organization(): void
    {
        Config::set('session.driver', 'database');
        app('session')->forgetDrivers();

        ['organization' => $org1, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $org2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->addUserToOrg($org2, $user, TenantRole::SalesManager->value);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertGreaterThanOrEqual(1, DB::table('sessions')->count());

        $this->postJson('/ajax/organizations/switch', ['organization_id' => $org2->id])
            ->assertOk();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($org2->name);
    }

    public function test_explicit_database_queue_connection_enqueues_and_processes_job(): void
    {
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'database');
        Cache::forgetDriver('database');
        app('queue')->setDefaultDriver('database');

        $markerKey = 'queue-worker-marker-'.uniqid();

        SharedDriverTestJob::dispatch($markerKey, 'processed-ok');

        $this->assertSame(1, DB::table('jobs')->count());

        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
        ]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('processed-ok', Cache::store('database')->get($markerKey));
    }
}
