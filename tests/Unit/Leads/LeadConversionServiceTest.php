<?php

namespace Tests\Unit\Leads;

use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Services\LinkShortenerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class LeadConversionServiceTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_b2b_conversion_matches_or_creates_account_contact_deal_and_updates_touchpoints(): void
    {
        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $existingAccount = Account::create([
            'organization_id' => $org->id,
            'name' => 'Acme Corporation',
        ]);

        $link = app(LinkShortenerService::class)->create($org, [
            'name' => 'LinkedIn Campaign',
            'destination_url' => 'https://example.test/b2b',
            'channel' => 'social',
        ]);

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2B',
            'status' => 'qualified',
            'first_name' => 'Elena',
            'last_name' => 'Vance',
            'email' => 'elena@acme.test',
            'company_name' => 'acme corporation',
            'job_title' => 'CTO',
            'employee_count' => 250,
            'assigned_user_id' => $tenant['user']->id,
        ]);

        $tp = Touchpoint::create([
            'organization_id' => $org->id,
            'tracking_link_id' => $link->id,
            'lead_id' => $lead->id,
            'event_milestone' => 'lead_captured',
            'occurred_at' => now(),
        ]);

        $result = app(LeadConversionService::class)->convert($lead, LeadConversionData::fromArray([
            'create_deal' => true,
            'deal_title' => 'Acme Annual Contract',
            'deal_amount' => 24000,
        ]));

        $this->assertNotNull($result['account']);
        $this->assertSame((int) $existingAccount->id, (int) $result['account']->id);
        $this->assertSame((int) $existingAccount->id, (int) $result['contact']->account_id);
        $this->assertNotNull($result['deal']);
        $this->assertSame((int) $existingAccount->id, (int) $result['deal']->account_id);
        $this->assertSame((int) $result['contact']->id, (int) $result['deal']->contact_id);
        $this->assertSame('converted', $result['lead']->status);
        $this->assertSame((int) $result['contact']->id, (int) $result['lead']->converted_contact_id);
        $this->assertSame((int) $result['deal']->id, (int) $result['lead']->converted_deal_id);

        $tp->refresh();
        $this->assertSame((int) $result['contact']->id, (int) $tp->contact_id);
        $this->assertSame((int) $result['deal']->id, (int) $tp->deal_id);
    }

    public function test_b2c_conversion_creates_standalone_contact_without_account(): void
    {
        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2C',
            'status' => 'new',
            'first_name' => 'Liam',
            'last_name' => 'Carter',
            'email' => 'liam@consumer.test',
            'location' => 'Austin, TX',
        ]);

        $result = app(LeadConversionService::class)->convert($lead, LeadConversionData::fromArray([
            'create_deal' => false,
        ]));

        $this->assertNull($result['account']);
        $this->assertNull($result['deal']);
        $this->assertNull($result['contact']->account_id);
        $this->assertNull($result['lead']->converted_account_id);
        $this->assertSame('converted', $result['lead']->status);
    }

    public function test_double_conversion_and_cross_tenant_pipeline_are_rejected_atomically(): void
    {
        $tenantA = $this->createOrgWithUser();
        $tenantB = $this->createOrgWithUser();

        $leadA = Lead::create([
            'organization_id' => $tenantA['organization']->id,
            'type' => 'B2C',
            'status' => 'new',
            'first_name' => 'Cross',
            'email' => 'cross@a.test',
        ]);

        $pipelineB = Pipeline::withoutGlobalScopes()
            ->where('organization_id', $tenantB['organization']->id)
            ->firstOrFail();

        $service = app(LeadConversionService::class);

        try {
            $service->convert($leadA, LeadConversionData::fromArray([
                'create_deal' => true,
                'pipeline_id' => $pipelineB->id,
            ]));
            $this->fail('Expected ValidationException for cross-tenant pipeline.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pipeline_id', $e->errors());
        }

        // Verify transaction rolled back Contact creation.
        $this->assertSame(0, Contact::withoutGlobalScopes()->where('organization_id', $tenantA['organization']->id)->count());

        $service->convert($leadA, LeadConversionData::fromArray(['create_deal' => false]));

        $this->expectException(ValidationException::class);
        $service->convert($leadA->fresh(), LeadConversionData::fromArray(['create_deal' => false]));
    }
}
