<?php

namespace Tests\E2E\Tier2;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\E2E\Support\E2ETestCase;

class F05InvitationsAndApiTokensBoundaryTest extends E2ETestCase
{
    public function test_t2_f05_01_non_org_admin_cannot_invite_create_tokens_or_change_roles(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'NonAdmin RBAC Org']);
        $org = $tenant['organization'];

        $salesManager = $this->createMemberInOrganization($org->id, 'sales_manager')['user'];
        $rep = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($salesManager, $org->id);

        $this->postJson('/ajax/organizations/invite', [
            'email' => 'blocked-invite@acme.test',
            'role' => 'sales_rep',
        ])->assertStatus(403);

        $this->postJson('/ajax/team/tokens', [
            'name' => 'Blocked Token',
        ])->assertStatus(403);

        $this->patchJson("/ajax/team/members/{$rep->id}/role", [
            'role' => 'org_admin',
        ])->assertStatus(403);
    }

    public function test_t2_f05_02_expired_or_already_accepted_invitation_is_rejected(): void
    {
        Mail::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Expired Invite Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $this->postJson('/ajax/organizations/invite', [
            'email' => 'expired@acme.test',
            'role' => 'sales_rep',
        ])->assertStatus(201);

        $this->postJson('/ajax/organizations/invite', [
            'email' => 'accepted@acme.test',
            'role' => 'sales_rep',
        ])->assertStatus(201);

        $expiredToken = DB::table('organization_invitations')
            ->where('organization_id', $org->id)
            ->where('email', 'expired@acme.test')
            ->value('token');

        DB::table('organization_invitations')
            ->where('token', $expiredToken)
            ->update(['expires_at' => now()->subDay()]);

        $acceptedToken = DB::table('organization_invitations')
            ->where('organization_id', $org->id)
            ->where('email', 'accepted@acme.test')
            ->value('token');

        DB::table('organization_invitations')
            ->where('token', $acceptedToken)
            ->update(['accepted_at' => now()->subHour()]);

        $expiredUser = User::create([
            'name' => 'Expired Invitee',
            'email' => 'expired@acme.test',
            'password' => 'Password123!',
        ]);

        $acceptedUser = User::create([
            'name' => 'Accepted Invitee',
            'email' => 'accepted@acme.test',
            'password' => 'Password123!',
        ]);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();

        $expiredRes = $this->actingAs($expiredUser)->postJson("/invitations/{$expiredToken}/accept");
        $this->assertStatusIn($expiredRes, [404, 410, 422]);
        $this->assertNull($expiredUser->roleIn($org));

        $acceptedRes = $this->actingAs($acceptedUser)->postJson("/invitations/{$acceptedToken}/accept");
        $this->assertStatusIn($acceptedRes, [404, 410, 422]);
        $this->assertNull($acceptedUser->roleIn($org));
    }

    public function test_t2_f05_03_inviting_existing_member_or_invalid_role_is_rejected(): void
    {
        Mail::fake();

        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Dup Invite Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $existingMember = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $dupRes = $this->postJson('/ajax/organizations/invite', [
            'email' => $existingMember->email,
            'role' => 'sales_rep',
        ]);
        $dupRes->assertStatus(422);

        $badRoleRes = $this->postJson('/ajax/organizations/invite', [
            'email' => 'x@acme.test',
            'role' => 'super_hacker',
        ]);
        $badRoleRes->assertStatus(422);
    }

    public function test_t2_f05_04_sole_org_admin_cannot_demote_or_remove_self(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Sole Admin Guard Org']);
        $orgAdmin = $tenant['user'];
        $org = $tenant['organization'];

        $this->actingAsInOrganization($orgAdmin, $org->id);

        $demoteRes = $this->patchJson("/ajax/team/members/{$orgAdmin->id}/role", [
            'role' => 'sales_rep',
        ]);
        $this->assertStatusIn($demoteRes, [403, 422]);

        $deleteRes = $this->deleteJson("/ajax/team/members/{$orgAdmin->id}");
        $this->assertStatusIn($deleteRes, [403, 422]);

        $this->assertSame('org_admin', $orgAdmin->roleIn($org));
    }

    public function test_t2_f05_05_invalid_or_cross_org_bearer_token_is_rejected(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Token Org A']);
        $orgAAdmin = $tenantA['user'];
        $orgA = $tenantA['organization'];

        $orgB = $this->registerOrganizationAndAdmin(['organization_name' => 'Token Org B'])['organization'];

        $tokenRes = $this->actingAsInOrganization($orgAAdmin, $orgA->id)
            ->postJson('/ajax/team/tokens', ['name' => 'Org A Key']);
        $this->assertStatusIn($tokenRes, [200, 201]);

        $data = $this->extractJsonData($tokenRes);
        $orgAToken = $data['plain_text_token']
            ?? (is_string($data['token'] ?? null) ? $data['token'] : '');

        $this->assertNotSame('', $orgAToken);

        Auth::logout();
        $this->clearTenantHeader()->clearTenantContextIfBound();
        $this->flushSession();

        $invalidRes = $this->getJson('/api/me', [
            'Authorization' => 'Bearer invalid-token-123',
        ]);
        $invalidRes->assertStatus(401);

        $crossOrgRes = $this->getJson('/api/me', [
            'Authorization' => "Bearer {$orgAToken}",
            'X-Organization-ID' => (string) $orgB->id,
        ]);
        $this->assertStatusIn($crossOrgRes, [401, 403]);
    }
}
