<?php

namespace Tests\E2E\Tier1;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\E2E\Support\E2ETestCase;

class F05InvitationsAndApiTokensTest extends E2ETestCase
{
    public function test_t1_f05_01_org_admin_sends_invitation_to_new_teammate(): void
    {
        Mail::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Invite Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $response = $this->actingAsInOrganization($orgAdmin, $org->id)
            ->postJson('/ajax/organizations/invite', [
                'email' => 'newrep@acme.test',
                'role' => 'sales_rep',
            ]);

        $this->assertStatusIn($response, [200, 201]);

        $invitation = DB::table('organization_invitations')
            ->where('organization_id', $org->id)
            ->where('email', 'newrep@acme.test')
            ->first();

        $this->assertNotNull($invitation);
        $this->assertSame('sales_rep', $invitation->role);
        $this->assertNull($invitation->accepted_at);
    }

    public function test_t1_f05_02_invited_user_accepts_invitation_and_joins_with_role(): void
    {
        Mail::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Accept Invite Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $inviteRes = $this->actingAsInOrganization($orgAdmin, $org->id)
            ->postJson('/ajax/organizations/invite', [
                'email' => 'invitee@acme.test',
                'role' => 'sales_manager',
            ]);
        $this->assertStatusIn($inviteRes, [200, 201]);

        $plainToken = DB::table('organization_invitations')
            ->where('organization_id', $org->id)
            ->where('email', 'invitee@acme.test')
            ->value('token');

        $this->assertNotEmpty($plainToken);

        $inviteeUser = User::create([
            'name' => 'Invited Manager',
            'email' => 'invitee@acme.test',
            'password' => 'Password123!',
        ]);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();

        $acceptRes = $this->actingAs($inviteeUser)
            ->flushSession()
            ->post("/invitations/{$plainToken}/accept");

        $this->assertStatusIn($acceptRes, [200, 201, 302]);

        $this->assertSame('sales_manager', $inviteeUser->roleIn($org));

        $acceptedAt = DB::table('organization_invitations')
            ->where('token', $plainToken)
            ->value('accepted_at');

        $this->assertNotNull($acceptedAt);
    }

    public function test_t1_f05_03_org_admin_updates_member_role_and_removes_member(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Roster Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $updateRes = $this->patchJson("/ajax/team/members/{$rep->id}/role", [
            'role' => 'sales_manager',
        ]);
        $updateRes->assertStatus(200);
        $this->assertSame('sales_manager', $rep->roleIn($org));

        $deleteRes = $this->deleteJson("/ajax/team/members/{$rep->id}");
        $this->assertStatusIn($deleteRes, [200, 204]);
        $this->assertNull($rep->roleIn($org));
    }

    public function test_t1_f05_04_org_admin_issues_api_token_and_authenticates_requests(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Token Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $tokenRes = $this->actingAsInOrganization($orgAdmin, $org->id)
            ->postJson('/ajax/team/tokens', ['name' => 'Zapier Sync']);

        $this->assertStatusIn($tokenRes, [200, 201]);

        $data = $this->extractJsonData($tokenRes);
        $plainToken = $data['plain_text_token']
            ?? (is_string($data['token'] ?? null) ? $data['token'] : null);

        $this->assertIsString($plainToken);
        $this->assertNotSame('', trim($plainToken));

        $storedHash = DB::table('api_tokens')
            ->where('organization_id', $org->id)
            ->where('name', 'Zapier Sync')
            ->value('token_hash');

        $this->assertNotEmpty($storedHash);
        $this->assertNotSame($plainToken, $storedHash);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->flushSession();

        $meRes = $this->getJson('/api/me', [
            'Authorization' => "Bearer {$plainToken}",
            'X-Organization-ID' => (string) $org->id,
        ]);

        $meRes->assertStatus(200);
        $meRes->assertJsonPath('user.email', $orgAdmin->email);
        $meRes->assertJsonPath('organization.id', $org->id);
        $meRes->assertJsonPath('organization.slug', $org->slug);
    }

    public function test_t1_f05_05_team_settings_page_renders_roster_and_token_ui(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Team UI Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $member = $this->createMemberInOrganization($org->id, 'sales_rep', [
            'name' => 'Roster Teammate',
            'email' => 'teammate@acme.test',
        ])['user'];

        $response = $this->actingAsInOrganization($orgAdmin, $org->id)
            ->get('/settings/team');

        $response->assertStatus(200);
        $response->assertSee($member->name);
        $response->assertSee('teammate@acme.test');
        $this->assertNoViteAssets($response);
    }
}
