<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F04AuthAndRbacBoundaryTest extends E2ETestCase
{
    public function test_t2_f04_01_read_only_user_is_blocked_from_all_mutations(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'ReadOnly Block Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload(['first_name' => 'KeepOriginal']));
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactId = $this->extractJsonData($contactRes)['id'];

        $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->get();

        $this->assertGreaterThanOrEqual(2, $stages->count());
        $stage1Id = $stages[0]->id;
        $stage2Id = $stages[1]->id;

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'ReadOnly Guard Deal',
            'amount' => 4500,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stage1Id,
            'contact_id' => $contactId,
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $dealId = $this->extractJsonData($dealRes)['id'];

        $leadsBefore = DB::table('leads')->where('organization_id', $org->id)->count();

        $this->actingAsInOrganization($readOnly, $org->id);

        $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(403);
        $this->putJson("/ajax/contacts/{$contactId}", ['first_name' => 'Hack'])->assertStatus(403);
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $stage2Id])->assertStatus(403);
        $this->postJson('/ajax/whatsapp/conversations/1/send-text', ['body' => 'Unauthorized'])->assertStatus(403);

        $this->assertSame($leadsBefore, DB::table('leads')->where('organization_id', $org->id)->count());
        $this->assertSame('KeepOriginal', DB::table('contacts')->where('id', $contactId)->value('first_name'));
        $this->assertSame((int) $stage1Id, (int) DB::table('deals')->where('id', $dealId)->value('pipeline_stage_id'));
    }

    public function test_t2_f04_02_sales_rep_is_blocked_from_admin_and_manager_endpoints(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Rep Boundary Org']);
        $org = $tenant['organization'];

        $salesRep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($salesRep, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'rep_forbidden',
            'label' => 'Rep Forbidden',
            'data_type' => 'text',
        ])->assertStatus(403);

        $this->postJson('/ajax/team/members', [
            'name' => 'Rogue Member',
            'email' => 'rogue@acme.test',
            'password' => 'Password123!',
            'role' => 'sales_rep',
        ])->assertStatus(403);

        $this->postJson('/ajax/whatsapp/connect', [
            'code' => 'meta_oauth_code',
        ])->assertStatus(403);

        $this->postJson('/ajax/pipelines', [
            'name' => 'Unauthorized Pipeline',
        ])->assertStatus(403);
    }

    public function test_t2_f04_03_sales_rep_cannot_modify_another_reps_assigned_lead_or_deal(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Peer Rep Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $rep1 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $rep2 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $leadRes = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => 'Rep2 Protected Co',
            'assigned_user_id' => $rep2->id,
        ]));
        $this->assertStatusIn($leadRes, [200, 201]);
        $rep2LeadId = $this->extractJsonData($leadRes)['id'];

        $this->patchJson("/ajax/leads/{$rep2LeadId}/assign", ['assigned_user_id' => $rep2->id])->assertStatus(200);

        $contactRes = $this->postJson('/ajax/contacts', $this->validContactPayload());
        $this->assertStatusIn($contactRes, [200, 201]);
        $contactId = $this->extractJsonData($contactRes)['id'];

        $pipelineId = DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')
            ->where('pipeline_id', $pipelineId)
            ->where('category', 'open')
            ->orderBy('order_index')
            ->get();

        $this->assertGreaterThanOrEqual(2, $stages->count());
        $stage1Id = $stages[0]->id;
        $stage2Id = $stages[1]->id;

        $dealRes = $this->postJson('/ajax/deals', [
            'title' => 'Rep2 Protected Deal',
            'amount' => 9000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stage1Id,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep2->id,
        ]);
        $this->assertStatusIn($dealRes, [200, 201]);
        $rep2DealId = $this->extractJsonData($dealRes)['id'];

        $this->actingAsInOrganization($rep1, $org->id);

        $this->patchJson("/ajax/deals/{$rep2DealId}/stage", ['pipeline_stage_id' => $stage2Id])->assertStatus(403);
        $this->putJson("/ajax/leads/{$rep2LeadId}", ['company_name' => 'Hijacked Co'])->assertStatus(403);

        $this->assertSame((int) $stage1Id, (int) DB::table('deals')->where('id', $rep2DealId)->value('pipeline_stage_id'));
        $this->assertSame('Rep2 Protected Co', DB::table('leads')->where('id', $rep2LeadId)->value('company_name'));
    }

    public function test_t2_f04_04_invalid_login_credentials_fail_without_authenticating(): void
    {
        $created = $this->registerOrganizationAndAdmin([
            'email' => 'valid-user@acme.test',
            'password' => 'Password123!',
        ]);
        $user = $created['user'];

        $this->post('/logout');
        $this->assertGuest();

        $wrongPasswordRes = $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPass',
        ]);
        $this->assertStatusIn($wrongPasswordRes, [302, 401, 422]);
        $this->assertGuest();

        $unknownEmailRes = $this->post('/login', [
            'email' => 'nobody@example.test',
            'password' => 'WrongPass',
        ]);
        $this->assertStatusIn($unknownEmailRes, [302, 401, 422]);
        $this->assertGuest();
    }

    public function test_t2_f04_05_switching_to_non_member_organization_is_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Owned Switch Org A']);
        $user = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $orgC = $this->registerOrganizationAndAdmin(['organization_name' => 'Forbidden Switch Org C'])['organization'];

        $response = $this->actingAsInOrganization($user, $orgA->id)
            ->postJson('/ajax/organizations/switch', [
                'organization_id' => $orgC->id,
            ]);

        $response->assertStatus(403);
        $this->assertSame((int) $orgA->id, (int) session('active_organization_id'));
    }
}
