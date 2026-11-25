<?php

namespace Tests\E2E\Tier1;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\E2E\Support\E2ETestCase;
use Tests\E2E\Support\WhatsAppWebhookHelper;

class F21NotificationEngineTest extends E2ETestCase
{
    public function test_t1_f21_01_lead_assigned_persists_db_notification_and_dispatches_mail_channel(): void
    {
        $sentEvents = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sentEvents): void {
            $sentEvents[] = [
                'channel' => $event->channel,
                'class' => get_class($event->notification),
                'notifiable_id' => (int) $event->notifiable->getKey(),
            ];
        });

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Lead Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $leadId = (int) $this->extractJsonData(
            $this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201)
        )['id'];

        $this->patchJson("/ajax/leads/{$leadId}/assign", [
            'assigned_user_id' => $rep->id,
        ])->assertStatus(200);

        $this->assertTrue(Schema::hasTable('notifications'), 'notifications table must exist.');

        $rows = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%LeadAssignedNotification%')
            ->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());
        $this->assertNull($rows->first()->read_at);

        $channels = collect($sentEvents)
            ->filter(fn (array $e) => $e['notifiable_id'] === (int) $rep->id && str_contains($e['class'], 'LeadAssignedNotification'))
            ->pluck('channel')
            ->all();

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_t1_f21_02_inbound_whatsapp_on_assigned_contact_deal_notifies_owner(): void
    {
        config(['services.meta.app_secret' => 'secret_notif_wa']);
        $this->enableDatabaseDrivers();

        $sentEvents = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sentEvents): void {
            $sentEvents[] = [
                'channel' => $event->channel,
                'class' => get_class($event->notification),
                'notifiable_id' => (int) $event->notifiable->getKey(),
            ];
        });

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif WA Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $waModel = 'App\\Modules\\Communications\\WhatsApp\\Models\\WhatsAppAccount';
        $waModel::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_notif_21',
            'phone_number_id' => 'pn_notif_21',
            'display_phone_number' => '+15552100002',
            'access_token_encrypted' => 'enc-notif-21',
            'is_active' => true,
        ]);

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload(['phone' => '+15559210002']))->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $openStageId = (int) DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->value('id');

        $this->postJson('/ajax/deals', [
            'title' => 'WA Owner Deal',
            'amount' => 5000,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStageId,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201);

        Auth::logout();
        $this->clearTenantHeader()->withSession([]);

        $rawJson = WhatsAppWebhookHelper::encode(
            WhatsAppWebhookHelper::inboundTextPayload('pn_notif_21', '+15559210002', 'When can we sign?', 'wamid.NOTIF2102')
        );
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => WhatsAppWebhookHelper::signPayload($rawJson, 'secret_notif_wa'),
        ], $rawJson)->assertStatus(200);

        $this->drainDatabaseQueue();

        $rows = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%InboundWhatsAppNotification%')
            ->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $channels = collect($sentEvents)
            ->filter(fn (array $e) => $e['notifiable_id'] === (int) $rep->id && str_contains($e['class'], 'InboundWhatsAppNotification'))
            ->pluck('channel')
            ->all();
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_t1_f21_03_deal_stage_changed_persists_db_notification_and_dispatches_mail(): void
    {
        $sentEvents = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sentEvents): void {
            $sentEvents[] = [
                'channel' => $event->channel,
                'class' => get_class($event->notification),
                'notifiable_id' => (int) $event->notifiable->getKey(),
            ];
        });

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Deal Stage Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $contactId = (int) $this->extractJsonData(
            $this->postJson('/ajax/contacts', $this->validContactPayload())->assertStatus(201)
        )['id'];

        $pipelineId = (int) DB::table('pipelines')->where('organization_id', $org->id)->value('id');
        $stages = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->where('category', 'open')->orderBy('order_index')->get();
        $this->assertGreaterThanOrEqual(2, $stages->count());

        $dealId = (int) $this->extractJsonData($this->postJson('/ajax/deals', [
            'title' => 'Stage Notif Deal',
            'amount' => 7500,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stages[0]->id,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        $this->patchJson("/ajax/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $stages[1]->id,
        ])->assertStatus(200);

        $rows = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%DealStageChangedNotification%')
            ->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $channels = collect($sentEvents)
            ->filter(fn (array $e) => $e['notifiable_id'] === (int) $rep->id && str_contains($e['class'], 'DealStageChangedNotification'))
            ->pluck('channel')
            ->all();
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_t1_f21_04_deal_sla_warning_command_notifies_owner_on_stale_open_deal(): void
    {
        $sentEvents = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sentEvents): void {
            $sentEvents[] = [
                'channel' => $event->channel,
                'class' => get_class($event->notification),
                'notifiable_id' => (int) $event->notifiable->getKey(),
            ];
        });

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif SLA Org']);
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
            'title' => 'Stale SLA Deal',
            'amount' => 9500,
            'currency' => 'USD',
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $openStage->id,
            'contact_id' => $contactId,
            'assigned_user_id' => $rep->id,
        ])->assertStatus(201))['id'];

        DB::table('deals')->where('id', $dealId)->update([
            'stage_entered_at' => now()->subDays($staleDays + 3),
        ]);

        $this->assertSame(0, $this->invokeSlaCommand(), 'SLA check command must exit with 0.');
        $this->assertTrue((bool) DB::table('deals')->where('id', $dealId)->value('is_stale'));

        $rows = DB::table('notifications')
            ->where('notifiable_id', $rep->id)
            ->where('type', 'like', '%DealSlaWarningNotification%')
            ->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $channels = collect($sentEvents)
            ->filter(fn (array $e) => $e['notifiable_id'] === (int) $rep->id && str_contains($e['class'], 'DealSlaWarningNotification'))
            ->pluck('channel')
            ->all();
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_t1_f21_05_list_mark_single_read_and_mark_all_read_via_ajax_endpoints(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Notif Bell AJAX Org']);
        $admin = $tenant['user'];
        $org = $tenant['organization'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($admin, $org->id);
        $lead1Id = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];
        $lead2Id = (int) $this->extractJsonData($this->postJson('/ajax/leads', $this->validB2BLeadPayload())->assertStatus(201))['id'];

        $this->patchJson("/ajax/leads/{$lead1Id}/assign", ['assigned_user_id' => $rep->id])->assertStatus(200);
        $this->patchJson("/ajax/leads/{$lead2Id}/assign", ['assigned_user_id' => $rep->id])->assertStatus(200);

        $this->actingAsInOrganization($rep, $org->id);

        $listRes = $this->getJson('/ajax/notifications');
        $listRes->assertStatus(200);
        $payload = $listRes->json();
        $this->assertSame(2, (int) ($payload['unread_count'] ?? -1));

        $notifications = $payload['notifications'] ?? $this->extractJsonData($listRes);
        $this->assertCount(2, $notifications);
        $firstId = $notifications[0]['id'];

        $this->patchJson("/ajax/notifications/{$firstId}/read")->assertStatus(200);
        $this->assertNotNull(DB::table('notifications')->where('id', $firstId)->value('read_at'));

        $afterOne = $this->getJson('/ajax/notifications')->assertStatus(200)->json();
        $this->assertSame(1, (int) ($afterOne['unread_count'] ?? -1));

        $this->postJson('/ajax/notifications/read-all')->assertStatus(200);

        $afterAll = $this->getJson('/ajax/notifications')->assertStatus(200)->json();
        $this->assertSame(0, (int) ($afterAll['unread_count'] ?? -1));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $rep->id)->whereNull('read_at')->count());
    }
}
