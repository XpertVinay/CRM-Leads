<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F01OrganizationProvisioningBoundaryTest extends E2ETestCase
{
    public function test_t2_f01_01_duplicate_organization_slug_is_rejected(): void
    {
        $first = $this->registerOrganizationAndAdmin([
            'organization_name' => 'Acme Corp',
            'organization_slug' => 'acme-corp',
        ]);
        $user = $first['user'];

        $this->actingAs($user)->post('/organizations', [
            'name' => 'Acme Slug Holder',
            'slug' => 'acme-slug-dup',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ])->assertStatus(302);

        $beforeCount = DB::table('organizations')->where('slug', 'acme-slug-dup')->count();
        $this->assertSame(1, $beforeCount);

        $response = $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Another Acme',
            'slug' => 'acme-slug-dup',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
        $this->assertSame(1, DB::table('organizations')->where('slug', 'acme-slug-dup')->count());
    }

    public function test_t2_f01_02_duplicate_registration_identifier_or_primary_email_is_rejected(): void
    {
        $user = $this->registerOrganizationAndAdmin()['user'];

        $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Unique Org Base',
            'slug' => 'unique-org-base',
            'registration_identifier' => 'REG-100',
            'primary_contact_email' => 'admin@acme.test',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ])->assertStatus(201);

        $initialTotal = DB::table('organizations')->count();

        $dupRegRes = $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Dup Reg Org',
            'slug' => 'dup-reg-org',
            'registration_identifier' => 'REG-100',
            'primary_contact_email' => 'unique1@acme.test',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
        $dupRegRes->assertStatus(422);
        $dupRegRes->assertJsonValidationErrors(['registration_identifier']);

        $dupEmailRes = $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Dup Email Org',
            'slug' => 'dup-email-org',
            'registration_identifier' => 'REG-200',
            'primary_contact_email' => 'admin@acme.test',
            'timezone' => 'UTC',
            'currency' => 'USD',
        ]);
        $dupEmailRes->assertStatus(422);
        $dupEmailRes->assertJsonValidationErrors(['primary_contact_email']);

        $this->assertSame($initialTotal, DB::table('organizations')->count());
    }

    public function test_t2_f01_03_invalid_currency_or_timezone_is_rejected(): void
    {
        $user = $this->registerOrganizationAndAdmin()['user'];
        $initialCount = DB::table('organizations')->count();

        $badCurrencyRes = $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Bad Currency Org',
            'slug' => 'bad-currency-org',
            'timezone' => 'UTC',
            'currency' => 'USDD',
        ]);
        $badCurrencyRes->assertStatus(422);
        $badCurrencyRes->assertJsonValidationErrors(['currency']);

        $badTimezoneRes = $this->actingAs($user)->postJson('/organizations', [
            'name' => 'Bad Timezone Org',
            'slug' => 'bad-timezone-org',
            'timezone' => 'Invalid/Zone',
            'currency' => 'USD',
        ]);
        $badTimezoneRes->assertStatus(422);
        $badTimezoneRes->assertJsonValidationErrors(['timezone']);

        $this->assertSame($initialCount, DB::table('organizations')->count());
    }

    public function test_t2_f01_04_suspended_organization_blocks_tenant_access(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Suspended Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $org->update(['status' => 'suspended']);

        $this->actingAsInOrganization($admin, $org->id);

        $this->get('/leads')->assertStatus(403);
        $this->getJson('/ajax/leads', $this->tenantHeaders($org->id))->assertStatus(403);
    }

    public function test_t2_f01_05_cancelled_organization_blocks_ajax_mutations(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Cancelled Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $org->update(['status' => 'cancelled']);

        $response = $this->actingAsInOrganization($admin, $org->id)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload(), $this->tenantHeaders($org->id));

        $response->assertStatus(403);
        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
    }
}
