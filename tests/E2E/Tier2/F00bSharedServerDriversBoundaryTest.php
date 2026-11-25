<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #2: Shared-Server Database Drivers.
 */
class F00bSharedServerDriversBoundaryTest extends E2ETestCase
{
    public function test_t2_f00b_01_cache_config_enforces_serializable_classes_false_and_blocks_php_object_unserialization(): void
    {
        $this->enableDatabaseDrivers();

        $this->assertFalse(config('cache.serializable_classes'));

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'No Object Cache Org']);
        $user = $tenant['user'];

        Cache::store('database')->put('e2e:object-probe', $user, 60);
        $cachedValue = Cache::store('database')->get('e2e:object-probe');

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $cachedValue);
    }

    public function test_t2_f00b_02_custom_field_registry_invalidates_database_cache_on_update_and_delete(): void
    {
        $this->enableDatabaseDrivers();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Cache Invalidation Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $createRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'tier_code',
            'label' => 'Original Label',
            'data_type' => 'text',
        ]);
        $createRes->assertStatus(201);
        $defId = (int) $this->extractJsonData($createRes)['id'];

        $registry = app('App\\Modules\\CustomFields\\Services\\CustomFieldRegistry');
        $cachedInitial = $registry->forEntity('lead', (int) $org->id);
        $this->assertCount(1, $cachedInitial);
        $this->assertSame('Original Label', $cachedInitial->first()->label);

        $this->putJson("/ajax/custom-fields/{$defId}", [
            'label' => 'Updated Label',
        ])->assertStatus(200);

        $cachedAfterUpdate = $registry->forEntity('lead', (int) $org->id);
        $this->assertCount(1, $cachedAfterUpdate);
        $this->assertSame('Updated Label', $cachedAfterUpdate->first()->label);

        $this->deleteJson("/ajax/custom-fields/{$defId}")->assertStatus(200);

        $cachedAfterDelete = $registry->forEntity('lead', (int) $org->id);
        $this->assertCount(0, $cachedAfterDelete);
    }

    public function test_t2_f00b_03_database_session_invalidates_cleanly_on_logout(): void
    {
        $this->enableDatabaseDrivers();

        $tenant = $this->registerOrganizationAndAdmin([
            'organization_name' => 'DB Logout Session Org',
            'email' => 'dblogout@example.test',
            'password' => 'Password123!',
        ]);

        auth()->logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->post('/login', [
            'email' => 'dblogout@example.test',
            'password' => 'Password123!',
        ])->assertStatus(302);

        $this->assertGreaterThanOrEqual(1, DB::table('sessions')->count());
        $this->get('/dashboard')->assertStatus(200);

        $this->post('/logout')->assertStatus(302);
        $this->assertGuest();

        $afterLogout = $this->get('/dashboard');
        $afterLogout->assertStatus(302);
        $afterLogout->assertRedirect('/login');
    }

    public function test_t2_f00b_04_empty_database_queue_drain_and_schema_tables_operate_without_redis(): void
    {
        $this->enableDatabaseDrivers();

        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));

        $this->assertSame(0, DB::table('jobs')->count());
        $this->drainDatabaseQueue();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_t2_f00b_05_custom_field_registry_isolates_cache_keys_per_org_and_ignores_unknown_entity(): void
    {
        $this->enableDatabaseDrivers();

        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Cache Iso Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Cache Iso Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id)
            ->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'org_a_key',
                'label' => 'Org A Key',
                'data_type' => 'text',
            ])->assertStatus(201);

        $this->actingAsInOrganization($adminB, $orgB->id)
            ->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'org_b_key',
                'label' => 'Org B Key',
                'data_type' => 'text',
            ])->assertStatus(201);

        $registry = app('App\\Modules\\CustomFields\\Services\\CustomFieldRegistry');

        $this->assertNotSame(
            $registry->cacheKey((int) $orgA->id, 'lead'),
            $registry->cacheKey((int) $orgB->id, 'lead')
        );

        $fieldsA = $registry->forEntity('lead', (int) $orgA->id);
        $fieldsB = $registry->forEntity('lead', (int) $orgB->id);
        $invalidEntity = $registry->forEntity('nonexistent_entity', (int) $orgA->id);

        $this->assertSame(['org_a_key'], $fieldsA->pluck('field_key')->all());
        $this->assertSame(['org_b_key'], $fieldsB->pluck('field_key')->all());
        $this->assertCount(0, $invalidEntity);
    }
}
