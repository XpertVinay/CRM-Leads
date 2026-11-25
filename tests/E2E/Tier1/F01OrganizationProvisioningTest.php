<?php

namespace Tests\E2E\Tier1;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\E2E\Support\E2ETestCase;

class F01OrganizationProvisioningTest extends E2ETestCase
{
    public function test_t1_f01_01_self_register_creates_user_organization_and_org_admin(): void
    {
        $response = $this->post('/register', [
            'name' => 'Acme Founder',
            'email' => 'founder@acme.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'Acme HQ',
            'organization_slug' => 'acme-hq',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'registration_identifier' => 'REG-ACME-01',
            'primary_contact_email' => 'ops@acme.test',
        ]);

        $this->assertStatusIn($response, [200, 201, 302]);

        $user = User::query()->where('email', 'founder@acme.test')->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertAuthenticatedAs($user);

        $organization = $user->organizations()->where('slug', 'acme-hq')->first()
            ?? $user->organizations()->latest('organizations.id')->first();

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertTrue(Str::isUuid((string) $organization->uuid), 'Organization UUID must be valid.');
        $this->assertSame('active', $organization->status);
        $this->assertSame('acme-hq', $organization->slug);
        $this->assertSame('America/New_York', $organization->timezone);
        $this->assertSame('USD', $organization->currency);
        $this->assertSame('org_admin', $user->roleIn($organization));

        $this->assertTrue(Schema::hasTable('pipelines'), 'pipelines table must exist for default seeding.');
        $this->assertTrue(Schema::hasTable('pipeline_stages'), 'pipeline_stages table must exist for default seeding.');

        $pipelineIds = DB::table('pipelines')
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $this->assertGreaterThanOrEqual(1, $pipelineIds->count());

        $stages = DB::table('pipeline_stages')->whereIn('pipeline_id', $pipelineIds)->get();
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'open')->count());
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'won')->where('win_probability', 100)->count());
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'lost')->where('win_probability', 0)->count());
    }

    public function test_t1_f01_02_authenticated_user_creates_secondary_organization(): void
    {
        $first = $this->registerOrganizationAndAdmin();
        $user = $first['user'];

        $response = $this->actingAs($user)->post('/organizations', [
            'name' => 'Beta Corp',
            'slug' => 'beta-corp',
            'timezone' => 'UTC',
            'currency' => 'EUR',
            'registration_identifier' => 'REG-BETA-991',
            'primary_contact_email' => 'ops@beta.test',
        ]);

        $this->assertStatusIn($response, [200, 201, 302]);

        $beta = Organization::query()->where('slug', 'beta-corp')->first();
        $this->assertInstanceOf(Organization::class, $beta);
        $this->assertSame('Beta Corp', $beta->name);
        $this->assertSame('active', $beta->status);
        $this->assertSame('EUR', $beta->currency);
        $this->assertSame('REG-BETA-991', $beta->registration_identifier);
        $this->assertSame('ops@beta.test', $beta->primary_contact_email);
        $this->assertSame('org_admin', $user->roleIn($beta));

        $this->assertTrue(Schema::hasTable('pipelines'), 'pipelines table must exist.');
        $this->assertGreaterThanOrEqual(
            1,
            DB::table('pipelines')->where('organization_id', $beta->id)->count()
        );
    }

    public function test_t1_f01_03_lists_only_organizations_belonging_to_authenticated_user(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Alpha Tenant']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Bravo Tenant',
            'slug' => 'bravo-tenant',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ])->assertStatus(302);

        $orgC = $this->registerOrganizationAndAdmin([
            'organization_name' => 'Secret Gamma Org',
            'organization_slug' => 'secret-gamma-org',
        ])['organization'];

        $response = $this->actingAsInOrganization($user, $orgA->id)->get('/organizations');

        $response->assertStatus(200);
        $response->assertSee($orgA->name);
        $response->assertSee('Bravo Tenant');
        $response->assertDontSee($orgC->name);
        $response->assertDontSee('secret-gamma-org');
    }

    public function test_t1_f01_04_default_pipeline_stages_seeded_on_organization_creation(): void
    {
        $created = $this->registerOrganizationAndAdmin(['organization_name' => 'Pipeline Seed Co']);
        $user = $created['user'];
        $org = $created['organization'];

        $pipelinesPage = $this->actingAsInOrganization($user, $org->id)->get('/pipelines');
        $pipelinesPage->assertStatus(200);

        $this->assertTrue(Schema::hasTable('pipelines'));
        $this->assertTrue(Schema::hasTable('pipeline_stages'));

        $pipelineIds = DB::table('pipelines')
            ->where('organization_id', $org->id)
            ->pluck('id');

        $this->assertGreaterThanOrEqual(1, $pipelineIds->count());

        $stages = DB::table('pipeline_stages')->whereIn('pipeline_id', $pipelineIds)->get();
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'open')->count());
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'won')->where('win_probability', 100)->count());
        $this->assertGreaterThanOrEqual(1, $stages->where('category', 'lost')->where('win_probability', 0)->count());
    }

    public function test_t1_f01_05_organization_soft_delete_excludes_from_active_access(): void
    {
        $first = $this->registerOrganizationAndAdmin(['organization_name' => 'Active Org']);
        $user = $first['user'];
        $orgA = $first['organization'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Soft Deleted Org',
            'slug' => 'soft-deleted-org',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $orgB = Organization::query()->where('slug', 'soft-deleted-org')->firstOrFail();
        $orgB->delete();

        $this->assertNull(Organization::query()->find($orgB->id));
        $this->assertNotNull(Organization::withTrashed()->find($orgB->id));

        $listResponse = $this->actingAsInOrganization($user, $orgA->id)->get('/organizations');
        $listResponse->assertStatus(200);
        $listResponse->assertSee('Active Org');
        $listResponse->assertDontSee('Soft Deleted Org');

        $blockedHeaderResponse = $this->actingAs($user)
            ->withSession(['active_organization_id' => $orgB->id])
            ->getJson('/ajax/custom-fields', $this->tenantHeaders($orgB->id));
        $blockedHeaderResponse->assertStatus(403);

        $blockedSessionResponse = $this->clearTenantHeader()
            ->actingAs($user)
            ->withSession(['active_organization_id' => $orgB->id])
            ->get('/dashboard');
        $blockedSessionResponse->assertStatus(403);
    }
}
