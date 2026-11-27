<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tracking\Models\TrackingLink;
use App\Support\Queries\SqlSearchHelper;
use App\Support\Security\CsvSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class SecurityAndDryHardeningTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_csv_sanitizer_neutralizes_formula_prefixes(): void
    {
        $this->assertSame("'=CMD()", CsvSanitizer::sanitizeCell('=CMD()'));
        $this->assertSame("'+1+1", CsvSanitizer::sanitizeCell('+1+1'));
        $this->assertSame("'-2+3", CsvSanitizer::sanitizeCell('-2+3'));
        $this->assertSame("'@SUM(A1)", CsvSanitizer::sanitizeCell('@SUM(A1)'));
        $this->assertSame("\t=1", ltrim(CsvSanitizer::sanitizeCell("\t=1"), "'"));
        $this->assertSame('Normal Name', CsvSanitizer::sanitizeCell('Normal Name'));
        $this->assertSame('', CsvSanitizer::sanitizeCell(null));
    }

    public function test_sql_search_helper_escapes_like_wildcards(): void
    {
        $this->assertSame('%100\\%\\_test\\\\x%', SqlSearchHelper::containsNeedle('100%_TEST\\x'));
    }

    public function test_activity_controller_enforces_model_authorization(): void
    {
        ['organization' => $org, 'user' => $readOnly] = $this->createOrgWithUser(TenantRole::ReadOnly->value);
        $this->withTenant($org);

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2C',
            'first_name' => 'Test',
            'source' => 'manual',
            'status' => 'new',
        ]);

        // ReadOnly cannot log activities (requires update permission)
        $this->actingAsOrgMember($readOnly, $org)
            ->postJson(route('ajax.activities.store'), [
                'activitable_type' => 'lead',
                'activitable_id' => $lead->id,
                'action' => 'note',
                'description' => 'Should be forbidden',
            ])
            ->assertForbidden();

        // ReadOnly can view activities
        $this->actingAsOrgMember($readOnly, $org)
            ->getJson(route('ajax.activities.index', [
                'activitable_type' => 'lead',
                'activitable_id' => $lead->id,
            ]))
            ->assertOk();
    }

    public function test_cross_tenant_foreign_keys_are_rejected_on_contact_deal_and_tracking_link(): void
    {
        ['organization' => $orgA, 'user' => $adminA] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $orgB] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $this->withTenant($orgB);
        $foreignAccount = Account::create(['organization_id' => $orgB->id, 'name' => 'Org B Account']);
        $foreignContact = Contact::create(['organization_id' => $orgB->id, 'first_name' => 'OrgB']);
        $foreignCampaign = Campaign::create([
            'organization_id' => $orgB->id,
            'name' => 'Org B Campaign',
            'platform' => 'meta',
            'type' => 'lead_gen',
            'status' => 'active',
        ]);
        $foreignPipeline = Pipeline::create(['organization_id' => $orgB->id, 'name' => 'Org B Pipe']);
        $foreignStage = PipelineStage::create([
            'pipeline_id' => $foreignPipeline->id,
            'name' => 'Open',
            'category' => 'open',
            'order_index' => 1,
        ]);

        // Contact cannot link to Org B account
        $this->actingAsOrgMember($adminA, $orgA)
            ->postJson(route('ajax.contacts.store'), [
                'first_name' => 'CrossTenant',
                'account_id' => $foreignAccount->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);

        // Deal cannot link to Org B contact/pipeline
        $this->actingAsOrgMember($adminA, $orgA)
            ->postJson(route('ajax.deals.store'), [
                'title' => 'CrossTenant Deal',
                'contact_id' => $foreignContact->id,
                'pipeline_id' => $foreignPipeline->id,
                'pipeline_stage_id' => $foreignStage->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contact_id', 'pipeline_id']);

        // TrackingLink cannot link to Org B campaign
        $this->actingAsOrgMember($adminA, $orgA)
            ->postJson(route('ajax.tracking-links.store'), [
                'name' => 'CrossTenant Link',
                'channel' => 'web',
                'destination_url' => 'https://example.com',
                'campaign_id' => $foreignCampaign->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    public function test_expired_tracking_link_returns_410_on_redirect(): void
    {
        ['organization' => $org] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'name' => 'Expired Link',
            'code' => 'expired123',
            'destination_url' => 'https://example.com',
            'channel' => 'web',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('tracking-links.redirect', $link->code))
            ->assertStatus(410);
    }
}
