<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 Boundary & Error E2E coverage for Feature #10: Dual-Mode B2B & B2C Lead Inbox.
 */
class F10DualModeLeadInboxBoundaryTest extends E2ETestCase
{
    public function test_t2_f10_01_b2b_lead_without_company_name_and_invalid_employee_count_rejected(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead B2B Validation Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $missingCompany = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'company_name' => '',
        ]));
        $missingCompany->assertStatus(422);

        $badEmployeeCount = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'employee_count' => 0,
        ]));
        $badEmployeeCount->assertStatus(422);

        $badType = $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
            'type' => 'ENTERPRISE',
        ]));
        $badType->assertStatus(422);

        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f10_02_invalid_email_missing_first_name_and_invalid_custom_fields_rejected(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Field Validation Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);

        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'region_code',
            'label' => 'Region Code',
            'data_type' => 'text',
            'is_required' => true,
        ])->assertStatus(201);

        $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'email' => 'not-an-email',
            'custom_fields' => ['region_code' => 'NA'],
        ]))->assertStatus(422);

        $this->postJson('/ajax/leads', $this->validB2CLeadPayload([
            'first_name' => '',
            'custom_fields' => ['region_code' => 'NA'],
        ]))->assertStatus(422);

        $this->postJson('/ajax/leads', $this->validB2CLeadPayload())
            ->assertStatus(422);

        $this->assertSame(0, DB::table('leads')->where('organization_id', $org->id)->count());
    }

    public function test_t2_f10_03_cross_organization_click_token_is_ignored_without_leaking_touchpoints(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Click Token Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Click Token Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminA, $orgA->id);
        $linkAId = (int) $this->extractJsonData(
            $this->postJson('/ajax/tracking-links', [
                'name' => 'Org A Campaign',
                'code' => 'orga-camp',
                'destination_url' => 'https://orga.test',
                'channel' => 'ad',
            ])->assertStatus(201)
        )['id'];

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->withSession([]);

        $this->get('/l/orga-camp')->assertStatus(302);
        $clickTokenA = (string) DB::table('link_clicks')
            ->where('tracking_link_id', $linkAId)
            ->value('click_token');

        $leadBRes = $this->actingAsInOrganization($adminB, $orgB->id)
            ->withCookie('click_token', $clickTokenA)
            ->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'click_token' => $clickTokenA,
            ]));
        $leadBRes->assertStatus(201);

        $this->assertSame(
            0,
            DB::table('touchpoints')
                ->where('organization_id', $orgB->id)
                ->where('event_milestone', 'lead_captured')
                ->count()
        );
        $this->assertSame(
            0,
            DB::table('touchpoints')
                ->where('organization_id', $orgA->id)
                ->where('event_milestone', 'lead_captured')
                ->count()
        );
    }

    public function test_t2_f10_04_sales_rep_cannot_assign_or_edit_peer_lead_and_read_only_blocked(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead RBAC Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $rep1 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $rep2 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload([
                'assigned_user_id' => $rep2->id,
            ]))->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($rep1, $org->id);
        $this->putJson("/ajax/leads/{$leadId}", ['job_title' => 'Hijacked'])->assertStatus(403);
        $this->patchJson("/ajax/leads/{$leadId}/assign", ['assigned_user_id' => $rep1->id])->assertStatus(403);
        $this->deleteJson("/ajax/leads/{$leadId}")->assertStatus(403);

        $this->actingAsInOrganization($readOnly, $org->id);
        $this->get('/leads')->assertStatus(200);
        $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(403);
        $this->putJson("/ajax/leads/{$leadId}", ['status' => 'qualified'])->assertStatus(403);
    }

    public function test_t2_f10_05_cross_organization_lead_access_and_cross_org_assignee_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Lead Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($adminA, $orgA->id);
        $leadAId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->assertStatusIn($this->get("/leads/{$leadBId}"), [403, 404]);
        $this->assertStatusIn($this->putJson("/ajax/leads/{$leadBId}", ['status' => 'qualified']), [403, 404]);
        $this->assertStatusIn($this->patchJson("/ajax/leads/{$leadBId}/assign", ['assigned_user_id' => $adminA->id]), [403, 404]);
        $this->assertStatusIn($this->deleteJson("/ajax/leads/{$leadBId}"), [403, 404]);

        $crossAssign = $this->patchJson("/ajax/leads/{$leadAId}/assign", [
            'assigned_user_id' => $adminB->id,
        ]);
        $this->assertStatusIn($crossAssign, [403, 404, 422]);
        $this->assertNull(DB::table('leads')->where('id', $leadAId)->value('assigned_user_id'));
    }
}
