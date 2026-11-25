<?php

namespace Tests\Feature\Leads;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Leads\Livewire\ConvertLeadWizard;
use App\Modules\Leads\Livewire\LeadDetailModal;
use App\Modules\Leads\Livewire\LeadInboxTable;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class LeadInboxAndConversionTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_dual_mode_lead_crud_custom_fields_and_click_token_touchpoint(): void
    {
        $tenant = $this->createOrgWithUser();
        $org = $tenant['organization'];

        $this->actingAsOrgMember($tenant['user'], $org);

        CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'industry',
            'label' => 'Industry',
            'data_type' => 'select',
            'options' => ['Fintech', 'SaaS'],
            'is_required' => true,
        ]);

        // B2B missing company_name -> 422
        $this->postJson('/ajax/leads', [
            'type' => 'B2B',
            'first_name' => 'NoCompany',
            'custom_fields' => ['industry' => 'Fintech'],
        ])->assertStatus(422)->assertJsonValidationErrors(['company_name']);

        // Missing required custom field -> 422
        $this->postJson('/ajax/leads', [
            'type' => 'B2B',
            'first_name' => 'MissingCF',
            'company_name' => 'Acme',
        ])->assertStatus(422)->assertJsonValidationErrors(['industry']);

        // Click a tracking link first to capture click_token
        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'lead-src',
            'name' => 'Lead Src',
            'destination_url' => 'https://example.test/form',
            'channel' => 'ad',
        ]);
        $redirect = $this->get('/l/'.$link->code);
        // Extract click_token from the LinkClick record (avoids cookie encryption issues)
        $clickToken = \App\Modules\Tracking\Models\LinkClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->first()?->click_token;
        $this->assertNotNull($clickToken);

        $this->actingAsOrgMember($tenant['user'], $org);

        // Spoofed organization_id is ignored; click_token links lead_captured touchpoint.
        $b2bRes = $this->postJson('/ajax/leads', [
            'organization_id' => 999999,
            'type' => 'B2B',
            'first_name' => 'Elena',
            'last_name' => 'Vance',
            'email' => 'elena@fintech.test',
            'company_name' => 'Fintech Labs',
            'job_title' => 'VP Ops',
            'employee_count' => 80,
            'custom_fields' => ['industry' => 'Fintech'],
            'click_token' => $clickToken,
        ]);
        $b2bRes->assertStatus(201);
        $leadId = $b2bRes->json('data.id');
        $this->assertSame((int) $org->id, (int) $b2bRes->json('data.organization_id'));

        $this->assertTrue(
            Touchpoint::withoutGlobalScopes()
                ->where('lead_id', $leadId)
                ->where('tracking_link_id', $link->id)
                ->where('event_milestone', 'lead_captured')
                ->exists()
        );

        // Partial update without custom_fields preserves existing custom_fields
        $this->putJson("/ajax/leads/{$leadId}", ['job_title' => 'COO'])->assertStatus(200);
        $this->assertSame(['industry' => 'Fintech'], Lead::findOrFail($leadId)->custom_fields);

        $this->get("/leads/{$leadId}")->assertStatus(200)->assertSee('Fintech Labs');
        $this->get('/leads')->assertStatus(200);
    }

    public function test_lead_rbac_and_http_conversion_endpoints(): void
    {
        $tenant = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $org = $tenant['organization'];

        $manager = $this->createOrgWithUser(TenantRole::SalesManager->value)['user'];
        $this->addUserToOrg($org, $manager, TenantRole::SalesManager->value);

        $rep1 = $this->createOrgWithUser(TenantRole::SalesRep->value)['user'];
        $this->addUserToOrg($org, $rep1, TenantRole::SalesRep->value);

        $rep2 = $this->createOrgWithUser(TenantRole::SalesRep->value)['user'];
        $this->addUserToOrg($org, $rep2, TenantRole::SalesRep->value);

        $readOnly = $this->createOrgWithUser(TenantRole::ReadOnly->value)['user'];
        $this->addUserToOrg($org, $readOnly, TenantRole::ReadOnly->value);

        $this->actingAsOrgMember($manager, $org);
        $leadRes = $this->postJson('/ajax/leads', [
            'type' => 'B2C',
            'first_name' => 'Sam',
            'email' => 'sam@consumer.test',
            'location' => 'Seattle, WA',
        ])->assertStatus(201);
        $leadId = $leadRes->json('data.id');

        $this->patchJson("/ajax/leads/{$leadId}/assign", ['assigned_user_id' => $rep2->id])->assertStatus(200);

        // Read-only cannot create or convert.
        $this->actingAsOrgMember($readOnly, $org);
        $this->getJson('/ajax/leads')->assertStatus(200);
        $this->postJson('/ajax/leads', ['type' => 'B2C', 'first_name' => 'X'])->assertStatus(403);
        $this->postJson("/ajax/leads/{$leadId}/convert", ['create_deal' => false])->assertStatus(403);

        // Rep1 cannot edit, assign, delete, or convert Rep2's lead.
        $this->actingAsOrgMember($rep1, $org);
        $this->putJson("/ajax/leads/{$leadId}", ['first_name' => 'Tamper'])->assertStatus(403);
        $this->patchJson("/ajax/leads/{$leadId}/assign", ['assigned_user_id' => $rep1->id])->assertStatus(403);
        $this->postJson("/ajax/leads/{$leadId}/convert", ['create_deal' => false])->assertStatus(403);
        $this->deleteJson("/ajax/leads/{$leadId}")->assertStatus(403);

        // Rep2 can convert their own assigned B2C lead.
        $this->actingAsOrgMember($rep2, $org);
        $convertRes = $this->postJson("/ajax/leads/{$leadId}/convert", [
            'create_deal' => true,
            'deal_title' => 'Sam Consumer Deal',
            'deal_amount' => 1500,
        ]);
        $convertRes->assertStatus(200);
        $this->assertNull($convertRes->json('data.account'));
        $this->assertNull($convertRes->json('data.contact.account_id'));
        $this->assertSame('Sam Consumer Deal', $convertRes->json('data.deal.title'));
    }

    public function test_livewire_lead_components_convert_and_enforce_cross_tenant_isolation(): void
    {
        $tenantA = $this->createOrgWithUser();
        $tenantB = $this->createOrgWithUser();

        $leadA = Lead::create([
            'organization_id' => $tenantA['organization']->id,
            'type' => 'B2B',
            'status' => 'new',
            'first_name' => 'Avery',
            'company_name' => 'Avery Tech',
            'email' => 'avery@tech.test',
        ]);

        $leadB = Lead::create([
            'organization_id' => $tenantB['organization']->id,
            'type' => 'B2B',
            'status' => 'new',
            'first_name' => 'Secret',
            'company_name' => 'Secret Corp',
        ]);

        $this->actingAsOrgMember($tenantA['user'], $tenantA['organization']);

        Livewire::actingAs($tenantA['user'])
            ->test(LeadInboxTable::class)
            ->assertSee('Avery Tech')
            ->assertDontSee('Secret Corp');

        Livewire::actingAs($tenantA['user'])
            ->test(LeadDetailModal::class)
            ->call('loadLead', $leadA->id)
            ->set('firstName', 'Avery Updated')
            ->call('saveLead')
            ->assertHasNoErrors();

        $this->assertSame('Avery Updated', $leadA->fresh()->first_name);

        Livewire::actingAs($tenantA['user'])
            ->test(ConvertLeadWizard::class)
            ->call('openWizard', $leadA->id)
            ->set('dealAmount', '7500')
            ->call('convertLead')
            ->assertHasNoErrors();

        $this->assertSame('converted', $leadA->fresh()->status);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($tenantA['user'])
            ->test(LeadDetailModal::class)
            ->call('loadLead', $leadB->id);
    }
}
