<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F22GdprAndAuditLogsBoundaryTest extends E2ETestCase
{
    public function test_t2_f22_01_non_admin_roles_blocked_from_gdpr_anonymize_and_audit_logs(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR RBAC Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $manager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['email' => 'keep@rbac.test']))->assertStatus(201)
        )['id'];

        foreach ([$manager, $rep, $readOnly] as $actor) {
            $this->actingAsInOrganization($actor, $org->id);

            $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(403);
            $this->get('/audit-logs')->assertStatus(403);
            $this->getJson('/ajax/audit-logs')->assertStatus(403);
        }

        $this->assertSame('keep@rbac.test', DB::table('contacts')->where('id', $contactId)->value('email'));
    }

    public function test_t2_f22_02_audit_log_records_are_immutable_against_update_and_delete(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Audit Immutable Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $this->postJson('/ajax/team/tokens', ['name' => 'Immutable Log Trigger'])->assertStatus(201);

        $auditLogClass = 'App\\Modules\\Compliance\\Models\\AuditLog';
        $this->assertTrue(class_exists($auditLogClass), 'AuditLog model must exist.');

        $log = $auditLogClass::query()->where('organization_id', $org->id)->latest('id')->firstOrFail();
        $originalEvent = (string) $log->event;

        $this->assertStatusIn($this->putJson("/ajax/audit-logs/{$log->id}", ['event' => 'tampered']), [403, 404, 405]);
        $this->assertStatusIn($this->deleteJson("/ajax/audit-logs/{$log->id}"), [403, 404, 405]);

        $updateBlocked = false;
        try {
            $result = $log->update(['event' => 'tampered_eloquent']);
            if ($result === false) {
                $updateBlocked = true;
            }
        } catch (\Throwable) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'AuditLog model must reject updates.');

        $deleteBlocked = false;
        try {
            $result = $log->delete();
            if ($result === false) {
                $deleteBlocked = true;
            }
        } catch (\Throwable) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'AuditLog model must reject deletes.');

        $persisted = DB::table('audit_logs')->where('id', $log->id)->first();
        $this->assertNotNull($persisted);
        $this->assertSame($originalEvent, (string) $persisted->event);
    }

    public function test_t2_f22_03_cross_organization_gdpr_export_and_anonymize_return_404_or_403(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'GDPR Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $contactBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['email' => 'protected-b@gdpr.test']))->assertStatus(201)
        )['id'];
        $leadBId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'lead-b@gdpr.test']))->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($adminA, $orgA->id);

        $this->assertStatusIn($this->getJson("/ajax/contacts/{$contactBId}/gdpr-export"), [403, 404]);
        $this->assertStatusIn($this->getJson("/ajax/leads/{$leadBId}/gdpr-export"), [403, 404]);
        $this->assertStatusIn($this->postJson("/ajax/contacts/{$contactBId}/gdpr-anonymize"), [403, 404]);

        $this->assertSame('protected-b@gdpr.test', DB::table('contacts')->where('id', $contactBId)->value('email'));
        $this->assertSame('lead-b@gdpr.test', DB::table('leads')->where('id', $leadBId)->value('email'));
    }

    public function test_t2_f22_04_double_anonymize_is_idempotent_and_preserves_deal_amount(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Double Anon Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['email' => 'twice@gdpr.test']))->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');
        $wonStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'won')->value('id');

        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Double Anon Deal',
            'amount' => 12345.67,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
        ])->assertStatus(201))['id'];
        $this->patchJson("/ajax/deals/{$dealId}/stage", ['pipeline_stage_id' => $wonStageId])->assertStatus(200);
        $closedAt = DB::table('deals')->where('id', $dealId)->value('closed_at');

        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);
        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(200);

        $contact = DB::table('contacts')->where('id', $contactId)->first();
        $this->assertSame('Anonymized', $contact->first_name);
        $this->assertNull($contact->last_name);
        $this->assertNotSame('twice@gdpr.test', $contact->email);

        $deal = DB::table('deals')->where('id', $dealId)->first();
        $this->assertSame(12345.67, (float) $deal->amount);
        $this->assertSame((string) $closedAt, (string) $deal->closed_at);
    }

    public function test_t2_f22_05_read_only_user_is_blocked_from_gdpr_anonymize_export_and_audit_logs(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'ReadOnly GDPR Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $readOnly = $this->createMemberInOrganization($org->id, 'read_only')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['email' => 'ro-protect@gdpr.test']))->assertStatus(201)
        )['id'];
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload(['email' => 'ro-lead@gdpr.test']))->assertStatus(201)
        )['id'];

        $this->actingAsInOrganization($readOnly, $org->id);

        $this->postJson("/ajax/contacts/{$contactId}/gdpr-anonymize")->assertStatus(403);
        $this->getJson("/ajax/contacts/{$contactId}/gdpr-export")->assertStatus(403);
        $this->getJson("/ajax/leads/{$leadId}/gdpr-export")->assertStatus(403);
        $this->get('/audit-logs')->assertStatus(403);
        $this->getJson('/ajax/audit-logs')->assertStatus(403);

        $this->assertSame('ro-protect@gdpr.test', DB::table('contacts')->where('id', $contactId)->value('email'));
    }
}
