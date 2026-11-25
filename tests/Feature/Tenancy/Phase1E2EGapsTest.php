<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Modules\Tenancy\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class Phase1E2EGapsTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_register_persists_org_metadata_and_seeds_five_ordered_pipeline_stages(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Founder',
            'email' => 'jane@gaptest.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'GapTest Org',
            'organization_slug' => 'GapTest HQ!',
            'timezone' => 'America/New_York',
            'currency' => 'eur',
            'registration_identifier' => 'REG-GAP-01',
            'primary_contact_email' => 'ops@gaptest.test',
        ]);

        $response->assertRedirect('/dashboard');

        $org = Organization::query()->where('slug', 'gaptest-hq')->firstOrFail();
        $this->assertSame('America/New_York', $org->timezone);
        $this->assertSame('EUR', $org->currency);
        $this->assertSame('REG-GAP-01', $org->registration_identifier);
        $this->assertSame('ops@gaptest.test', $org->primary_contact_email);

        $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $this->assertNotNull($pipelineId);

        $stages = DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->orderBy('order_index')
            ->get();

        $this->assertCount(5, $stages);
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Won', 'Lost'], $stages->pluck('name')->all());
        $this->assertSame(3, $stages->where('category', 'open')->count());
        $this->assertSame(100, (int) $stages->firstWhere('category', 'won')->win_probability);
        $this->assertSame(0, (int) $stages->firstWhere('category', 'lost')->win_probability);
    }

    public function test_organization_store_and_update_enforce_timezone_all_rule(): void
    {
        $tenant = $this->createOrgWithUser();

        $this->actingAsOrgMember($tenant['user'], $tenant['organization']);

        $storeRes = $this->postJson('/organizations', [
            'name' => 'Bad TZ Org',
            'slug' => 'bad-tz-org',
            'timezone' => 'Mars/Olympus',
            'currency' => 'USD',
        ]);
        $storeRes->assertStatus(422)->assertJsonValidationErrors(['timezone']);

        $updateRes = $this->patchJson('/organizations/settings', [
            'name' => $tenant['organization']->name,
            'timezone' => 'Invalid/Timezone',
            'currency' => 'USD',
        ]);
        $updateRes->assertStatus(422)->assertJsonValidationErrors(['timezone']);
    }

    public function test_api_token_rejects_mismatched_or_malformed_x_organization_id_with_403(): void
    {
        $tenantA = $this->createOrgWithUser();
        $tenantB = $this->createOrgWithUser();

        $issued = ApiToken::issue($tenantA['organization'], $tenantA['user'], 'Test Token');
        $plain = $issued['plain_text_token'];

        $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$plain,
            'X-Organization-ID' => (string) $tenantB['organization']->id,
        ])->assertStatus(403);

        $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$plain,
            'X-Organization-ID' => 'not-a-number',
        ])->assertStatus(403);

        $this->assertNull($issued['token']->fresh()->last_used_at);

        $this->getJson('/api/me', [
            'Authorization' => 'Bearer '.$plain,
            'X-Organization-ID' => (string) $tenantA['organization']->id,
        ])->assertStatus(200);

        $this->assertNotNull($issued['token']->fresh()->last_used_at);
    }
}
