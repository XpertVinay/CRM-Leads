<?php

namespace Tests\Feature\WhatsAppCrm;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadAssignment;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class DynamicLeadCaptureTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_capture_page_renders_only_selected_custom_fields_with_required_flags(): void
    {
        ['organization' => $org] = $this->createOrgWithUser('org_admin');

        OrganizationSetting::forOrganization($org)->update([
            'whatsapp_phone' => '+91 98765 43210',
        ]);

        $budgetField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'monthly_budget',
            'label' => 'Monthly Budget',
            'data_type' => 'number',
            'is_required' => true,
            'order_index' => 1,
        ]);

        $channelsField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'social_channels',
            'label' => 'Social Channels',
            'data_type' => 'multiselect',
            'options' => ['Instagram', 'LinkedIn', 'YouTube'],
            'is_required' => false,
            'order_index' => 2,
        ]);

        $unselectedField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'internal_score',
            'label' => 'Internal Score',
            'data_type' => 'text',
            'is_required' => true,
            'order_index' => 3,
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'dyn1',
            'slug' => 'ig-campaign-1',
            'name' => 'Instagram Reel Promo',
            'destination_url' => url('/go/ig-campaign-1'),
            'channel' => 'social',
            'capture_custom_field_ids' => [$budgetField->id, $channelsField->id],
            'is_active' => true,
        ]);

        $response = $this->get('/go/ig-campaign-1?utm_source=instagram&utm_medium=paid_social&fbclid=fb_test_99');

        $response->assertOk();
        $response->assertSee('Monthly Budget');
        $response->assertSee('Social Channels');
        $response->assertSee('Instagram');
        $response->assertSee('LinkedIn');
        $response->assertDontSee('Internal Score');
        $response->assertSee('name="social_params[fbclid]" value="fb_test_99"', false);
        $response->assertSee('name="social_params[utm_source]" value="instagram"', false);
    }

    public function test_mandatory_custom_field_is_enforced_and_optional_field_can_be_omitted(): void
    {
        ['organization' => $org] = $this->createOrgWithUser('org_admin');

        OrganizationSetting::forOrganization($org)->update([
            'whatsapp_phone' => '+91 98765 43210',
        ]);

        $budgetField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'monthly_budget',
            'label' => 'Monthly Budget',
            'data_type' => 'number',
            'is_required' => true,
            'order_index' => 1,
        ]);

        $notesField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'preferred_tier',
            'label' => 'Preferred Tier',
            'data_type' => 'select',
            'options' => ['Starter', 'Growth', 'Enterprise'],
            'is_required' => false,
            'order_index' => 2,
        ]);

        // Unselected required field should NOT block public capture
        CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'internal_only_required',
            'label' => 'Internal Only Required',
            'data_type' => 'text',
            'is_required' => true,
            'order_index' => 3,
        ]);

        TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'dyn2',
            'slug' => 'ig-campaign-2',
            'name' => 'IG Lead Form',
            'destination_url' => url('/go/ig-campaign-2'),
            'channel' => 'social',
            'capture_custom_field_ids' => [$budgetField->id, $notesField->id],
            'is_active' => true,
        ]);

        // Missing mandatory custom field -> validation error
        $failResp = $this->post('/go/ig-campaign-2', [
            'full_name' => 'Aarav Mehta',
            'phone' => '+91 98111 22333',
            'custom_fields' => [],
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $failResp->assertSessionHasErrors('custom_fields.monthly_budget');
        $this->assertSame(0, Lead::withoutGlobalScopes()->where('organization_id', $org->id)->count());

        // Mandatory filled, optional omitted -> succeeds
        $okResp = $this->post('/go/ig-campaign-2', [
            'full_name' => 'Aarav Mehta',
            'phone' => '+91 98111 22333',
            'custom_fields' => [
                'monthly_budget' => '25000',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $okResp->assertStatus(302);

        $lead = Lead::withoutGlobalScopes()->where('organization_id', $org->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame(['monthly_budget' => 25000], $lead->custom_fields);
    }

    public function test_social_utms_custom_placeholders_and_auto_assignment_persist_end_to_end(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser('org_admin');
        $rep = User::factory()->create();
        $this->addUserToOrg($org, $rep, 'sales_rep');

        OrganizationSetting::forOrganization($org)->update([
            'whatsapp_phone' => '+91 98765 43210',
            'whatsapp_default_message' => 'Hi, I am {name} (Ref: {lead_ref}). Plan: {custom.plan_type}, Channels: {custom.channels}',
        ]);

        $planField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'plan_type',
            'label' => 'Plan Type',
            'data_type' => 'select',
            'options' => ['Pro', 'Enterprise'],
            'is_required' => true,
            'order_index' => 1,
        ]);

        $channelsField = CustomFieldDefinition::create([
            'organization_id' => $org->id,
            'entity_type' => 'lead',
            'field_key' => 'channels',
            'label' => 'Channels',
            'data_type' => 'multiselect',
            'options' => ['Instagram', 'LinkedIn'],
            'is_required' => false,
            'order_index' => 2,
        ]);

        $campaign = Campaign::create([
            'organization_id' => $org->id,
            'name' => 'Social Q4',
            'type' => 'paid_ads',
            'platform' => 'instagram',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'campaign_id' => $campaign->id,
            'code' => 'dyn3',
            'slug' => 'social-q4-ig',
            'name' => 'IG Story CTA',
            'destination_url' => url('/go/social-q4-ig'),
            'channel' => 'social',
            'capture_custom_field_ids' => [$planField->id, $channelsField->id],
            'assigned_user_id' => $rep->id,
            'is_active' => true,
        ]);

        $postResponse = $this->post('/go/social-q4-ig', [
            'full_name' => 'Neha Kapoor',
            'phone' => '+91 90000 11111',
            'custom_fields' => [
                'plan_type' => 'Pro',
                'channels' => ['Instagram', 'LinkedIn'],
            ],
            'social_params' => [
                'utm_source' => 'instagram',
                'utm_medium' => 'paid_social',
                'fbclid' => 'fb_click_abc',
                'igshid' => 'ig_shid_xyz',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $postResponse->assertStatus(302);
        $location = (string) $postResponse->headers->get('Location');
        $this->assertStringContainsString(rawurlencode('Plan: Pro, Channels: Instagram, LinkedIn'), $location);

        $lead = Lead::withoutGlobalScopes()->where('organization_id', $org->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('instagram', $lead->source);
        $this->assertSame($rep->id, (int) $lead->assigned_user_id);
        $this->assertSame([
            'plan_type' => 'Pro',
            'channels' => ['Instagram', 'LinkedIn'],
        ], $lead->custom_fields);

        $this->assertTrue(
            LeadAssignment::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('assigned_to_user_id', $rep->id)
                ->exists()
        );

        $touchpoint = Touchpoint::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('event_milestone', 'lead_captured')
            ->first();
        $this->assertNotNull($touchpoint);
        $this->assertSame('fb_click_abc', $touchpoint->metadata['social_params']['fbclid'] ?? null);
        $this->assertSame('ig_shid_xyz', $touchpoint->metadata['social_params']['igshid'] ?? null);

        $activity = Activity::withoutGlobalScopes()
            ->where('activitable_id', $lead->id)
            ->where('action', 'lead_captured')
            ->first();
        $this->assertNotNull($activity);
        $this->assertSame('instagram', $activity->metadata['utm_source'] ?? null);
        $this->assertSame('fb_click_abc', $activity->metadata['fbclid'] ?? null);
    }

    public function test_round_robin_auto_assignment_distributes_leads_across_org_members(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser('org_admin');
        $rep1 = User::factory()->create();
        $this->addUserToOrg($org, $rep1, 'sales_rep');

        OrganizationSetting::forOrganization($org)->update([
            'whatsapp_phone' => '+91 98765 43210',
        ]);

        TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'rr1',
            'slug' => 'round-robin-link',
            'name' => 'Round Robin Social',
            'destination_url' => url('/go/round-robin-link'),
            'channel' => 'social',
            'auto_assign_round_robin' => true,
            'is_active' => true,
        ]);

        $this->post('/go/round-robin-link', [
            'full_name' => 'Lead One',
            'phone' => '+91 91111 00001',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(302);

        $this->post('/go/round-robin-link', [
            'full_name' => 'Lead Two',
            'phone' => '+91 91111 00002',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(302);

        $assignedIds = Lead::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->orderBy('id')
            ->pluck('assigned_user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertCount(2, array_unique($assignedIds));
        $this->assertEqualsCanonicalizing([$admin->id, $rep1->id], $assignedIds);
    }
}
