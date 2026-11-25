<?php

namespace Tests\Feature\WhatsAppCrm;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadAssignment;
use App\Modules\Leads\Models\LeadStatusHistory;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class WhatsAppB2BCrmWorkflowTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_public_whatsapp_lead_capture_persists_lead_and_redirects_to_wa_me(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser('org_admin');

        OrganizationSetting::forOrganization($org)->update([
            'whatsapp_phone' => '+91 98765 43210',
            'whatsapp_default_message' => 'Hi, I am {name} (Ref: {lead_ref}) from {campaign}.',
            'lead_reference_prefix' => 'ACME',
        ]);

        $campaign = Campaign::create([
            'organization_id' => $org->id,
            'name' => 'Facebook Lead Gen Q4',
            'type' => 'paid_ads',
            'platform' => 'facebook',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $link = TrackingLink::create([
            'organization_id' => $org->id,
            'campaign_id' => $campaign->id,
            'code' => 'fbq4',
            'slug' => 'acme-facebook-q4',
            'name' => 'FB Q4 Ad',
            'destination_url' => url('/go/acme-facebook-q4'),
            'channel' => 'ad',
            'source' => 'facebook',
            'medium' => 'cpc',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'q4-launch',
            'is_active' => true,
        ]);

        // Step 1: Visit /go/{slug}
        $getResponse = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Referer' => 'https://l.facebook.com/',
        ])->get('/go/acme-facebook-q4');

        $getResponse->assertOk();
        $getResponse->assertSee($org->name);
        $getResponse->assertSee('Facebook Lead Gen Q4');
        $getResponse->assertCookie('click_token');

        $click = LinkClick::withoutGlobalScopes()->where('tracking_link_id', $link->id)->first();
        $this->assertNotNull($click);
        $this->assertSame('Mobile', $click->device);
        $this->assertSame('Safari', $click->browser);

        // Step 2 & 3: Submit form -> DB persistence -> wa.me redirect
        $idemKey = (string) Str::uuid();
        $postResponse = $this->post('/go/acme-facebook-q4', [
            'full_name' => 'Priya Sharma',
            'phone' => '+91 99887 76655',
            'email' => 'priya@example.com',
            'company_name' => 'Sharma Logistics',
            'requirement' => 'Need 50 WhatsApp CRM seats',
            'idempotency_key' => $idemKey,
        ]);

        $postResponse->assertStatus(302);
        $location = (string) $postResponse->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/919876543210?text=', $location);

        $lead = Lead::withoutGlobalScopes()->where('organization_id', $org->id)->first();
        $this->assertNotNull($lead);
        $this->assertStringStartsWith('ACME-', (string) $lead->lead_reference);
        $this->assertSame('Priya', $lead->first_name);
        $this->assertSame('Sharma', $lead->last_name);
        $this->assertSame('new', $lead->status);
        $this->assertSame($campaign->id, $lead->campaign_id);
        $this->assertSame($link->id, $lead->tracking_link_id);
        $this->assertStringContainsString(rawurlencode((string) $lead->lead_reference), $location);

        $this->assertTrue(
            Touchpoint::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('event_milestone', 'whatsapp_redirect')
                ->exists()
        );
        $this->assertTrue(
            LeadStatusHistory::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('to_status', 'new')
                ->exists()
        );

        // Step 4: Idempotency blocks duplicate double-submit with same key
        $doubleSubmit = $this->post('/go/acme-facebook-q4', [
            'full_name' => 'Priya Sharma',
            'phone' => '+91 99887 76655',
            'idempotency_key' => $idemKey,
        ]);
        $doubleSubmit->assertSessionHasErrors('idempotency');
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('organization_id', $org->id)->count());

        // Step 5: Duplicate mobile submission with a new form load merges history
        $dupSubmit = $this->post('/go/acme-facebook-q4', [
            'full_name' => 'Priya Sharma',
            'phone' => '9988776655',
            'requirement' => 'Follow up on pricing',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $dupSubmit->assertStatus(302);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $this->assertSame(1, (int) $lead->fresh()->duplicate_count);
        $this->assertTrue(
            Activity::withoutGlobalScopes()
                ->where('activitable_id', $lead->id)
                ->where('action', 'duplicate_inquiry')
                ->exists()
        );
    }

    public function test_inactive_or_expired_tracking_links_reject_lead_capture_with_410(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser('org_admin');

        $inactiveLink = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'dead1',
            'slug' => 'inactive-slug',
            'name' => 'Inactive Link',
            'destination_url' => url('/go/inactive-slug'),
            'channel' => 'ad',
            'is_active' => false,
        ]);

        $expiredLink = TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'exp1',
            'slug' => 'expired-slug',
            'name' => 'Expired Link',
            'destination_url' => url('/go/expired-slug'),
            'channel' => 'ad',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->get('/go/'.$inactiveLink->slug)->assertStatus(410);
        $this->post('/go/'.$inactiveLink->slug, [
            'full_name' => 'Test User',
            'phone' => '9876543210',
        ])->assertStatus(410);

        $this->get('/go/'.$expiredLink->slug)->assertStatus(410);
        $this->assertSame(0, Lead::withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }

    public function test_missing_whatsapp_number_does_not_redirect_or_persist_lead(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser('org_admin');

        TrackingLink::create([
            'organization_id' => $org->id,
            'code' => 'nowa',
            'slug' => 'no-wa-number',
            'name' => 'No WA Link',
            'destination_url' => url('/go/no-wa-number'),
            'channel' => 'website',
            'is_active' => true,
        ]);

        $response = $this->post('/go/no-wa-number', [
            'full_name' => 'Blocked Lead',
            'phone' => '9876543210',
        ]);

        $response->assertSessionHasErrors('whatsapp');
        $this->assertSame(0, Lead::withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }

    public function test_lead_lifecycle_bulk_actions_follow_ups_dashboard_and_tenant_isolation(): void
    {
        ['organization' => $orgA, 'user' => $adminA] = $this->createOrgWithUser('org_admin', [], ['name' => 'Admin A']);
        $repA = User::factory()->create(['name' => 'Exec A']);
        $viewerA = User::factory()->create(['name' => 'Viewer A']);
        $this->addUserToOrg($orgA, $repA, 'sales_rep');
        $this->addUserToOrg($orgA, $viewerA, 'read_only');

        ['organization' => $orgB, 'user' => $adminB] = $this->createOrgWithUser('org_admin', [], ['name' => 'Admin B']);

        $campaignA = Campaign::create([
            'organization_id' => $orgA->id,
            'name' => 'Org A LinkedIn',
            'type' => 'social',
            'platform' => 'linkedin',
            'status' => 'active',
        ]);

        $leadA = Lead::create([
            'organization_id' => $orgA->id,
            'campaign_id' => $campaignA->id,
            'type' => 'B2B',
            'status' => 'new',
            'first_name' => 'Rohan',
            'last_name' => 'Mehta',
            'phone' => '+919111122222',
            'company_name' => 'Mehta Tech',
            'source' => 'linkedin',
        ]);

        $leadB = Lead::create([
            'organization_id' => $orgB->id,
            'type' => 'B2B',
            'status' => 'new',
            'first_name' => 'OrgB',
            'phone' => '+919333344444',
            'company_name' => 'OrgB Corp',
            'source' => 'website',
        ]);

        // Status transition & assignment history in Org A
        $this->actingAsOrgMember($adminA, $orgA);

        $this->patchJson('/ajax/leads/'.$leadA->id, [
            'status' => 'qualified',
            'priority' => 'high',
            'status_reason' => 'Budget confirmed',
        ])->assertOk();

        $this->patchJson('/ajax/leads/'.$leadA->id.'/assign', [
            'assigned_user_id' => $repA->id,
        ])->assertOk();

        $this->assertTrue(LeadStatusHistory::withoutGlobalScopes()->where('lead_id', $leadA->id)->where('to_status', 'qualified')->exists());
        $this->assertTrue(LeadAssignment::withoutGlobalScopes()->where('lead_id', $leadA->id)->where('assigned_to_user_id', $repA->id)->exists());

        // Follow-up scheduling & completion
        $fuResp = $this->postJson('/ajax/follow-ups', [
            'lead_id' => $leadA->id,
            'assigned_user_id' => $repA->id,
            'type' => 'whatsapp',
            'due_at' => now()->addHour()->toDateTimeString(),
            'notes' => 'Send proposal on WhatsApp',
        ])->assertCreated();

        $fuId = $fuResp->json('data.id');
        $this->patchJson('/ajax/follow-ups/'.$fuId, [
            'status' => 'completed',
            'outcome' => 'Proposal sent',
            'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertOk();

        $this->assertSame(2, FollowUp::withoutGlobalScopes()->where('lead_id', $leadA->id)->count());

        // Dashboard metrics & global search
        $dashResp = $this->getJson('/ajax/dashboard/metrics')->assertOk();
        $this->assertSame(1, $dashResp->json('data.kpis.total_leads'));
        $this->assertSame(1, $dashResp->json('data.kpis.qualified_leads'));

        $searchResp = $this->getJson('/ajax/search?q=Rohan')->assertOk();
        $this->assertCount(1, $searchResp->json('data.leads'));

        // Viewer cannot create campaign or update org settings
        $this->actingAsOrgMember($viewerA, $orgA);
        $this->postJson('/ajax/campaigns', [
            'name' => 'Blocked',
            'type' => 'whatsapp',
            'platform' => 'whatsapp',
        ])->assertForbidden();
        $this->patchJson('/ajax/organizations/settings', [
            'duplicate_lead_strategy' => 'create_new',
            'lead_reference_prefix' => 'XX',
        ])->assertForbidden();

        // Org B cannot see Org A's campaign, lead, or search results
        $this->actingAsOrgMember($adminB, $orgB);
        $this->getJson('/ajax/search?q=Rohan')->assertOk()->assertJsonCount(0, 'data.leads');
        $this->patchJson('/ajax/leads/'.$leadA->id, ['status' => 'won'])->assertNotFound();
    }
}
