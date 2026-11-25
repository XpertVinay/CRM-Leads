<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #2: Shared-Server Database Drivers.
 */
class F00bSharedServerDriversTest extends E2ETestCase
{
    public function test_t1_f00b_01_database_drivers_enabled_persist_cache_rows_and_rate_limiter(): void
    {
        $this->enableDatabaseDrivers();

        $this->assertSame('database', config('queue.default'));
        $this->assertSame('database', config('cache.default'));
        $this->assertSame('database', config('session.driver'));

        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('jobs'));

        $payload = ['organization_id' => 42, 'items' => ['alpha', 'beta']];
        Cache::store('database')->put('e2e:shared-driver:plain', $payload, 60);

        $this->assertGreaterThanOrEqual(1, DB::table('cache')->count());
        $this->assertSame($payload, Cache::store('database')->get('e2e:shared-driver:plain'));

        $limiter = app(RateLimiter::class);
        $this->assertTrue($limiter->attempt('e2e:db-rate-limit', 2, static fn (): bool => true));
        $this->assertTrue($limiter->attempt('e2e:db-rate-limit', 2, static fn (): bool => true));
        $this->assertFalse($limiter->attempt('e2e:db-rate-limit', 2, static fn (): bool => true));
    }

    public function test_t1_f00b_02_custom_field_registry_caches_plain_arrays_and_hydrates_models_without_incomplete_class(): void
    {
        $this->enableDatabaseDrivers();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'DB Cache Registry Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'industry_sector',
                'label' => 'Industry Sector',
                'data_type' => 'select',
                'options' => ['SaaS', 'Healthcare', 'Fintech'],
                'is_required' => true,
            ])
            ->assertStatus(201);

        $registryClass = 'App\\Modules\\CustomFields\\Services\\CustomFieldRegistry';
        $definitionClass = 'App\\Modules\\CustomFields\\Models\\CustomFieldDefinition';
        $this->assertTrue(class_exists($registryClass));
        $this->assertTrue(class_exists($definitionClass));

        $registry = app($registryClass);
        $registry->forget((int) $org->id, 'lead');

        $firstLoad = $registry->forEntity('lead', (int) $org->id);
        $secondLoad = $registry->forEntity('lead', (int) $org->id);

        $this->assertCount(1, $firstLoad);
        $this->assertCount(1, $secondLoad);
        $this->assertInstanceOf($definitionClass, $secondLoad->first());
        $this->assertNotInstanceOf(\__PHP_Incomplete_Class::class, $secondLoad->first());
        $this->assertSame('industry_sector', $secondLoad->first()->field_key);
        $this->assertSame(['SaaS', 'Healthcare', 'Fintech'], $secondLoad->first()->options);
        $this->assertTrue((bool) $secondLoad->first()->is_required);
        $this->assertGreaterThanOrEqual(1, DB::table('cache')->count());
    }

    public function test_t1_f00b_03_database_session_driver_persists_active_organization_id_across_requests(): void
    {
        $this->enableDatabaseDrivers();

        $first = $this->registerOrganizationAndAdmin([
            'organization_name' => 'DB Session Org A',
            'email' => 'dbsession@example.test',
            'password' => 'Password123!',
        ]);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'DB Session Org B',
            'slug' => 'db-session-org-b',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ])->assertStatus(302);

        $orgB = $user->organizations()->where('slug', 'db-session-org-b')->firstOrFail();

        auth()->logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->post('/login', [
            'email' => 'dbsession@example.test',
            'password' => 'Password123!',
        ])->assertStatus(302);

        $this->assertGreaterThanOrEqual(1, DB::table('sessions')->count());

        $this->postJson('/ajax/organizations/switch', [
            'organization_id' => $orgB->id,
        ])->assertStatus(200);

        $this->clearTenantContextIfBound();

        $dashboardRes = $this->clearTenantHeader()->get('/dashboard');
        $dashboardRes->assertStatus(200);
        $dashboardRes->assertSee($orgB->name);
        $this->assertSame((int) $orgB->id, (int) session('active_organization_id'));
        $this->assertNotSame((int) $orgA->id, (int) session('active_organization_id'));
    }

    public function test_t1_f00b_04_database_queue_driver_enqueues_and_executes_jobs_via_artisan_worker(): void
    {
        $this->enableDatabaseDrivers();

        $jobClass = 'Tests\\Support\\SharedDriverTestJob';
        $this->assertTrue(class_exists($jobClass), 'SharedDriverTestJob must exist for database queue verification.');

        $markerKey = 'e2e:db-queue-marker-' . uniqid('', true);
        $jobClass::dispatch($markerKey, 'worker-completed');

        $this->assertSame(1, DB::table('jobs')->count());

        $this->drainDatabaseQueue();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('worker-completed', Cache::store('database')->get($markerKey));
    }

    public function test_t1_f00b_05_config_defaults_enforce_shared_server_drivers_and_json_session_serialization(): void
    {
        $cacheConfig = require base_path('config/cache.php');
        $queueConfig = require base_path('config/queue.php');
        $sessionConfig = require base_path('config/session.php');

        $this->assertFalse($cacheConfig['serializable_classes'] ?? true);
        $this->assertFalse(config('cache.serializable_classes'));

        $this->assertSame('json', $sessionConfig['serialization'] ?? null);
        $this->assertSame('json', config('session.serialization'));

        $this->assertArrayHasKey('database', $cacheConfig['stores'] ?? []);
        $this->assertArrayHasKey('database', $queueConfig['connections'] ?? []);
        $this->assertSame('jobs', $queueConfig['connections']['database']['table'] ?? null);
        $this->assertSame('sessions', $sessionConfig['table'] ?? null);
    }
}
