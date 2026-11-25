<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 E2E coverage for Feature #10: Dual-Mode B2B & B2C Lead Inbox.
 */
class F10DualModeLeadInboxTest extends E2ETestCase
{
    public function test_t1_f10_01_creates_b2b_and_b2c_leads_with_mode_specific_fields_and_custom_fields(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Dual Mode Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'budget_tier',
            'label' => 'Budget Tier',
            'data_type' => 'select',
            'options' => ['Starter', 'Enterprise'],
        ])->assertStatus(201);

        $b2bRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'Hyperion Systems',
            'job_title' => 'VP Engineering',
            'employee_count' => 250,
            'custom_fields' => ['budget_tier' => 'Enterprise'],
        ]));
        $b2bRes->assertStatus(201);
        $b2b = $this->extractJsonData($b2bRes);

        $this->assertSame('B2B', $b2b['type']);
        $this->assertSame('Hyperion Systems', $b2b['company_name']);
        $this->assertSame(250, (int) $b2b['employee_count']);
        $this->assertSame('Enterprise', $b2b['custom_fields']['budget_tier'] ?? null);

        $b2cRes = $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'first_name' => 'Maya',
            'location' => 'Denver, CO',
        ]));
        $b2cRes->assertStatus(201);
        $b2c = $this->extractJsonData($b2cRes);

        $this->assertSame('B2C', $b2c['type']);
        $this->assertSame('Denver, CO', $b2c['location']);

        $this->assertSame(2, DB::table('leads')->where('organization_id', $org->id)->count());
    }

    public function test_t1_f10_02_lists_filters_by_type_and_status_and_searches_leads(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Filter Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'AlphaTech',
            'status' => 'new',
        ]))->assertStatus(201);

        $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'ZetaCorp Industries',
            'status' => 'qualified',
        ]))->assertStatus(201);

        $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'first_name' => 'ConsumerMaya',
            'status' => 'new',
        ]))->assertStatus(201);

        $b2bList = $this->extractJsonData($this->getJson('/ajax/leads?type=B2B')->assertStatus(200));
        $this->assertCount(2, $b2bList);

        $b2cList = $this->extractJsonData($this->getJson('/ajax/leads?type=B2C')->assertStatus(200));
        $this->assertCount(1, $b2cList);
        $this->assertSame('ConsumerMaya', $b2cList[0]['first_name'] ?? null);

        $qualifiedList = $this->extractJsonData($this->getJson('/ajax/leads?status=qualified')->assertStatus(200));
        $this->assertCount(1, $qualifiedList);
        $this->assertSame('ZetaCorp Industries', $qualifiedList[0]['company_name'] ?? null);

        $searchList = $this->extractJsonData($this->getJson('/ajax/leads?search=ZetaCorp')->assertStatus(200));
        $this->assertCount(1, $searchList);

        $page = $this->get('/leads');
        $page->assertStatus(200);
        $this->assertNoViteAssets($page);
    }

    public function test_t1_f10_03_click_token_links_lead_captured_touchpoint_on_ingestion(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Click Attribution Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $linkId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Lead Ad',
                'code' => 'lead-ad-src',
                'destination_url' => 'https://example.test/signup',
                'channel' => 'ad',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->get('/l/lead-ad-src')->assertStatus(302);
        $clickRow = DB::table('link_clicks')->where('tracking_link_id', $linkId)->firstOrFail();

        $leadRes = $this->actingAsInOrganization($admin, $org->id)
            ->withCookie('click_token', (string) $clickRow->click_token)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'click_token' => (string) $clickRow->click_token,
            ]));
        $leadRes->assertStatus(201);
        $leadId = (int) $this->extractJsonData($leadRes)['id'];

        $capturedTouchpoint = DB::table('touchpoints')
            ->where('organization_id', $org->id)
            ->where('tracking_link_id', $linkId)
            ->where('link_click_id', $clickRow->id)
            ->where('lead_id', $leadId)
            ->where('event_milestone', 'lead_captured')
            ->first();

        $this->assertNotNull($capturedTouchpoint, 'Valid same-org click_token must record lead_captured touchpoint.');
        $this->assertSame(0, DB::table('deals')->where('organization_id', $org->id)->count());
    }

    public function test_t1_f10_04_manager_updates_and_assigns_lead_to_sales_rep(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Assign Org']);
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $salesRep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($manager, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->putJson("/ajax/leads/{$leadId}", [
            'status' => 'contacted',
            'job_title' => 'CTO',
        ])->assertStatus(200);

        $this->patchJson("/ajax/leads/{$leadId}/assign", [
            'assigned_user_id' => $salesRep->id,
        ])->assertStatus(200);

        $row = DB::table('leads')->where('id', $leadId)->first();
        $this->assertSame('contacted', $row->status);
        $this->assertSame('CTO', $row->job_title);
        $this->assertSame((int) $salesRep->id, (int) $row->assigned_user_id);

        $detailPage = $this->get("/leads/{$leadId}");
        $detailPage->assertStatus(200);
        $this->assertNoViteAssets($detailPage);
    }

    public function test_t1_f10_05_lead_inbox_table_and_detail_modal_livewire_components_work(): void
    {
        $this->assertTrue($this->hasLivewireInstalled());

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Livewire Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $lead = $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'first_name' => 'LivewireLead',
                'company_name' => 'LW Prospect Co',
            ]))->assertStatus(201)
        );

        $tenantContextClass = 'App\\Modules\\Tenancy\\Context\\TenantContext';
        if (class_exists($tenantContextClass)) {
            $this->app->make($tenantContextClass)->setTenant($org);
        }

        $inboxClass = $this->livewireClassIfExists('App\\Modules\\Leads\\Livewire\\LeadInboxTable');
        $this->assertNotNull($inboxClass, 'LeadInboxTable Livewire component must exist.');

        \Livewire\Livewire::actingAs($admin)
            ->test($inboxClass)
            ->assertSee('LW Prospect Co');

        $detailClass = $this->livewireClassIfExists('App\\Modules\\Leads\\Livewire\\LeadDetailModal');
        $this->assertNotNull($detailClass, 'LeadDetailModal Livewire component must exist.');

        \Livewire\Livewire::actingAs($admin)
            ->test($detailClass)
            ->call('loadLead', (int) $lead['id'])
            ->assertHasNoErrors()
            ->assertSee('LW Prospect Co');
    }
}
