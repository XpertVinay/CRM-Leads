<?php

namespace Tests\E2E\Tier1;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F04AuthAndRbacTest extends E2ETestCase
{
    public function test_t1_f04_01_user_logs_in_and_logs_out_cleanly(): void
    {
        $created = $this->registerOrganizationAndAdmin([
            'email' => 'login-check@acme.test',
            'password' => 'Password123!',
        ]);
        $user = $created['user'];

        $logoutFirst = $this->post('/logout');
        $this->assertStatusIn($logoutFirst, [200, 204, 302]);
        $this->assertGuest();

        $loginRes = $this->post('/login', [
            'email' => 'login-check@acme.test',
            'password' => 'Password123!',
        ]);
        $this->assertStatusIn($loginRes, [200, 302]);
        $this->assertAuthenticatedAs($user);

        $logoutRes = $this->post('/logout');
        $this->assertStatusIn($logoutRes, [200, 204, 302]);
        $this->assertGuest();
    }

    public function test_t1_f04_02_switching_organization_updates_role_permissions_dynamically(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Switch Org A']);
        $user = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Switch Org B']);
        $orgB = $tenantB['organization'];

        $this->createMemberInOrganization($orgB->id, 'read_only', [
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $adminPage = $this->actingAsInOrganization($user, $orgA->id)->get('/settings/team');
        $adminPage->assertStatus(200);

        $switchRes = $this->clearTenantHeader()->postJson('/ajax/organizations/switch', [
            'organization_id' => $orgB->id,
        ]);
        $switchRes->assertStatus(200);
        $this->assertSame((int) $orgB->id, (int) session('active_organization_id'));

        $this->clearTenantContextIfBound();

        $blockedPage = $this->get('/settings/team');
        $blockedPage->assertStatus(403);
    }

    public function test_t1_f04_03_org_admin_manages_settings_custom_fields_and_whatsapp(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Admin Caps Org']);
        $orgAdmin = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $this->actingAsInOrganization($orgAdmin, $orgA->id);

        $this->get('/settings/custom-fields')->assertStatus(200);

        $createFieldRes = $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'industry_segment',
            'label' => 'Industry Segment',
            'data_type' => 'text',
        ]);
        $this->assertStatusIn($createFieldRes, [200, 201]);

        $this->get('/settings/whatsapp')->assertStatus(200);
    }

    public function test_t1_f04_04_sales_manager_manages_pipelines_assigns_leads_and_views_analytics(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Manager Org']);
        $orgAdmin = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $salesManager = $this->createMemberInOrganization($orgA->id, 'sales_manager')['user'];
        $salesRep = $this->createMemberInOrganization($orgA->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($orgAdmin, $orgA->id);
        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload());
        $this->assertStatusIn($leadRes, [200, 201]);
        $leadId = $this->extractJsonData($leadRes)['id'];

        $this->actingAsInOrganization($salesManager, $orgA->id);

        $pipelineRes = $this->postJson('/ajax/pipelines', ['name' => 'Mid-Market']);
        $this->assertStatusIn($pipelineRes, [200, 201]);

        $assignRes = $this->patchJson("/ajax/leads/{$leadId}/assign", [
            'assigned_user_id' => $salesRep->id,
        ]);
        $assignRes->assertStatus(200);

        $this->get('/analytics/executive')->assertStatus(200);
    }

    public function test_t1_f04_05_sales_rep_works_assigned_deal_and_read_only_views_records(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Rep Org']);
        $orgAdmin = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $salesRep = $this->createMemberInOrganization($orgA->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($orgA->id, 'read_only')['user'];

        $this->actingAsInOrganization($orgAdmin, $orgA->id);

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload());
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactId = $this->extractJsonData($contactRes)['id'];

        $pipelineId = DB::table('pipelines')->where('organization_id', $orgA->id)->value('id');
        $stages = DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->get();

        $this->assertGreaterThanOrEqual(2, $stages->count(), 'Default pipeline must have at least 2 open stages.');
        $firstStageId = $stages[0]->id;
        $nextStageId = $stages[1]->id;

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'Rep Assigned Deal',
            'amount' => 12000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $firstStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $salesRep->id,
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $dealId = $this->extractJsonData($dealRes)['id'];

        $this->actingAsInOrganization($salesRep, $orgA->id)
            ->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $nextStageId])
            ->assertStatus(200);

        $this->actingAsInOrganization($readOnly, $orgA->id);
        $this->get('/leads')->assertStatus(200);
        $this->get('/deals')->assertStatus(200);
    }
}
