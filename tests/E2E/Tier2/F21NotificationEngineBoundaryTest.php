<?php

namespace Tests\E2E\Tier2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\E2E\Support\E2ETestCase;

class F21NotificationEngineBoundaryTest extends E2ETestCase
{
    public function test_t2_f21_01_marking_another_users_notification_in_same_org_returns_403_or_404(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Peer Guard Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep1 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $rep2 = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $this->patchJson("/ajax/leads/{$leadId}/assign", ['assigned_user_id' => $rep1->id])->assertStatus(200);

        $notifId = (string) DB::table('notifications')->where('notifiable_id', $rep1->id)->value('id');
        $this->assertNotSame('', $notifId);

        $this->actingAsInOrganization($rep2, $org->id);
        $res = $this->patchJson("/ajax/notifications/{$notifId}/read");
        $this->assertStatusIn($res, [403, 404]);

        $this->assertNull(DB::table('notifications')->where('id', $notifId)->value('read_at'));
    }

    public function test_t2_f21_02_marking_cross_tenant_notification_returns_403_or_404(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Cross A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Cross B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];
        $repB = $this->createMemberInOrganization($orgB->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadBId = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $this->patchJson("/ajax/leads/{$leadBId}/assign", ['assigned_user_id' => $repB->id])->assertStatus(200);

        $notifBId = (string) DB::table('notifications')->where('notifiable_id', $repB->id)->value('id');
        $this->assertNotSame('', $notifBId);

        $this->actingAsInOrganization($adminA, $orgA->id);
        $res = $this->patchJson("/ajax/notifications/{$notifBId}/read");
        $this->assertStatusIn($res, [403, 404]);

        $this->assertNull(DB::table('notifications')->where('id', $notifBId)->value('read_at'));
    }

    public function test_t2_f21_03_unauthenticated_requests_to_notification_endpoints_return_401(): void
    {
        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $this->getJson('/ajax/notifications')->assertStatus(401);
        $this->patchJson('/ajax/notifications/00000000-0000-0000-0000-000000000001/read')->assertStatus(401);
        $this->postJson('/ajax/notifications/read-all')->assertStatus(401);
    }

    public function test_t2_f21_04_repeat_sla_check_does_not_duplicate_notification_when_already_stale(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif SLA Idempotent Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStage = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->first();
        $staleDays = (int) ($openStage->stale_days ?? 14);

        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Idempotent SLA Deal',
            'amount' => 4200,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        DB::table('deals')->where('id', $dealId)->update([
            'stage_entered_at' => now()->subDays($staleDays + 5),
        ]);

        $this->assertSame(0, $this->invokeSlaCommand());
        $countAfterFirst = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%DealSlaWarningNotification%')
            ->count();
        $this->assertSame(1, $countAfterFirst);

        $this->assertSame(0, $this->invokeSlaCommand());
        $countAfterSecond = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%DealSlaWarningNotification%')
            ->count();
        $this->assertSame(1, $countAfterSecond, 'Repeat SLA check must not duplicate notification for already-stale deal.');
    }

    public function test_t2_f21_05_notifications_and_unread_count_are_scoped_per_user_and_tenant(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Scoped Notif Org A']);
        $adminA = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Scoped Notif Org B']);
        $adminB = $tenantB['user'];
        $orgB = $tenantB['organization'];

        $sharedMember = $this->createMemberInOrganization($orgA->id, 'sales_rep')['user'];
        $this->createMemberInOrganization($orgB->id, 'sales_rep', [
            'email' => $sharedMember->email,
            'name' => $sharedMember->name,
        ]);

        // 2 notifications in Org A
        $this->actingAsInOrganization($adminA, $orgA->id);
        $leadA1 = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $leadA2 = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $this->patchJson("/ajax/leads/{$leadA1}/assign", ['assigned_user_id' => $sharedMember->id])->assertStatus(200);
        $this->patchJson("/ajax/leads/{$leadA2}/assign", ['assigned_user_id' => $sharedMember->id])->assertStatus(200);

        // 1 notification in Org B
        $this->actingAsInOrganization($adminB, $orgB->id);
        $leadB1 = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $this->patchJson("/ajax/leads/{$leadB1}/assign", ['assigned_user_id' => $sharedMember->id])->assertStatus(200);

        // Inspect Org A scope
        $this->actingAsInOrganization($sharedMember, $orgA->id);
        $resA = $this->getJson('/ajax/notifications')->assertStatus(200)->json();
        $this->assertSame(2, (int) ($resA['unread_count'] ?? -1));

        // Mark all read in Org A -> must NOT mark Org B's notification read
        $this->postJson('/ajax/notifications/read-all')->assertStatus(200);

        $this->actingAsInOrganization($sharedMember, $orgB->id);
        $resB = $this->getJson('/ajax/notifications')->assertStatus(200)->json();
        $this->assertSame(1, (int) ($resB['unread_count'] ?? -1));
    }
}
