<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class RbacPermissionsTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_role_matrix_enforced_across_org_team_tokens_and_custom_fields(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $manager = User::factory()->create();
        $rep = User::factory()->create();
        $readOnly = User::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->addUserToOrg($org, $manager, TenantRole::SalesManager->value);
        $this->addUserToOrg($org, $rep, TenantRole::SalesRep->value);
        $this->addUserToOrg($org, $readOnly, TenantRole::ReadOnly->value);

        // Non-admin roles cannot access admin endpoints
        foreach ([$manager, $rep, $readOnly] as $nonAdmin) {
            $this->actingAsOrgMember($nonAdmin, $org);

            $this->get('/settings/team')->assertStatus(403);
            $this->get('/settings/custom-fields')->assertStatus(403);
            $this->patchJson('/organizations/settings', [
                'name' => 'Blocked Rename',
                'timezone' => 'UTC',
                'currency' => 'USD',
            ])->assertStatus(403);
            $this->postJson('/ajax/organizations/invite', [
                'email' => 'x@example.com',
                'role' => TenantRole::SalesRep->value,
            ])->assertStatus(403);
            $this->postJson('/ajax/team/tokens', ['name' => 'Blocked'])->assertStatus(403);
            $this->postJson('/ajax/custom-fields', [
                'entity_type' => 'lead',
                'field_key' => 'blocked_key',
                'label' => 'Blocked',
                'data_type' => 'text',
            ])->assertStatus(403);

            // But all org members can read custom field definitions list
            $this->getJson('/ajax/custom-fields')->assertOk();
        }

        // Org Admin succeeds on all admin routes
        $this->actingAsOrgMember($admin, $org);
        $this->get('/settings/team')->assertOk();
        $this->get('/settings/custom-fields')->assertOk();
        $this->patchJson('/organizations/settings', [
            'name' => 'Renamed Org',
            'timezone' => 'America/New_York',
            'currency' => 'EUR',
        ])->assertOk();

        // Superadmin bypasses role checks when tenant context is active
        $this->actingAsOrgMember($superadmin, $org);
        $this->get('/settings/team')->assertOk();
    }

    public function test_multi_org_user_loses_admin_rights_when_switching_to_read_only_org(): void
    {
        ['organization' => $org1, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $org2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->addUserToOrg($org2, $user, TenantRole::ReadOnly->value);

        $this->actingAsOrgMember($user, $org1);
        $this->get('/settings/team')->assertOk();

        $this->postJson('/ajax/organizations/switch', ['organization_id' => $org2->id])->assertOk();
        $this->get('/settings/team')->assertStatus(403);
        $this->postJson('/ajax/custom-fields', [
            'entity_type' => 'lead',
            'field_key' => 'nope',
            'label' => 'Nope',
            'data_type' => 'text',
        ])->assertStatus(403);
    }

    public function test_last_org_admin_cannot_be_demoted_or_removed(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $secondMember = User::factory()->create();
        $this->addUserToOrg($org, $secondMember, TenantRole::SalesRep->value);

        $this->actingAsOrgMember($admin, $org);

        // Cannot demote or remove sole org_admin
        $this->patchJson("/ajax/team/members/{$admin->id}/role", ['role' => TenantRole::SalesManager->value])
            ->assertStatus(422);
        $this->deleteJson("/ajax/team/members/{$admin->id}")
            ->assertStatus(422);

        // Promote secondMember to org_admin, then first admin CAN be demoted
        $this->patchJson("/ajax/team/members/{$secondMember->id}/role", ['role' => TenantRole::OrgAdmin->value])
            ->assertOk();
        $this->patchJson("/ajax/team/members/{$admin->id}/role", ['role' => TenantRole::SalesManager->value])
            ->assertOk();
    }

    public function test_invitation_and_api_token_lifecycle(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->actingAsOrgMember($admin, $org);

        // Invite user
        $inviteResponse = $this->postJson('/ajax/organizations/invite', [
            'email' => 'invitee@acme.test',
            'role' => TenantRole::SalesManager->value,
        ])->assertStatus(201);

        $token = $inviteResponse->json('invitation.token');
        $this->assertNotEmpty($token);

        // Wrong authenticated user cannot accept someone else's invitation
        $wrongUser = User::factory()->create(['email' => 'other@acme.test']);
        $this->actingAs($wrongUser)
            ->postJson("/invitations/{$token}/accept")
            ->assertStatus(403);

        // Correct user accepts invitation
        $invitee = User::factory()->create(['email' => 'invitee@acme.test']);
        $this->actingAs($invitee)
            ->postJson("/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('organization_id', $org->id);

        $this->assertSame(TenantRole::SalesManager->value, $invitee->roleIn($org));

        // Expired invitation fails with 410
        $expired = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $org->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'expired@acme.test',
            'role' => TenantRole::SalesRep->value,
            'token' => str_repeat('a', 64),
            'expires_at' => now()->subMinute(),
        ]);
        $this->get("/invitations/{$expired->token}/accept")->assertOk();
        $this->postJson("/invitations/{$expired->token}/accept", [
            'name' => 'Expired User',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertStatus(410);

        // API Token creation stores SHA-256 hash, never plaintext
        $this->actingAsOrgMember($admin, $org);
        $tokenResp = $this->postJson('/ajax/team/tokens', ['name' => 'CI Sync'])->assertStatus(201);
        $plainText = $tokenResp->json('plain_text_token');
        $tokenId = $tokenResp->json('token.id');

        $stored = ApiToken::withoutGlobalScope(OrganizationScope::class)->findOrFail($tokenId);
        $this->assertSame(hash('sha256', $plainText), $stored->token_hash);
        $this->assertNotSame($plainText, $stored->token_hash);

        // Valid Bearer token authenticates /api/me with tenant context
        $this->withHeaders(['Authorization' => "Bearer {$plainText}"])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('organization.id', $org->id);

        // Revoked token is rejected with 401
        $this->actingAsOrgMember($admin, $org);
        $this->deleteJson("/ajax/team/tokens/{$tokenId}")->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$plainText}"])
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_invitation_acceptance_returns_html_redirects_with_errors_for_browser_and_json_for_ajax(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $existingUser = User::factory()->create([
            'email' => 'browser-invitee@acme.test',
            'password' => 'CorrectPass123!',
        ]);
        $otherUser = User::factory()->create(['email' => 'wrong-user@acme.test']);

        $this->actingAsOrgMember($admin, $org);

        $inviteResp = $this->postJson('/ajax/organizations/invite', [
            'email' => 'browser-invitee@acme.test',
            'role' => TenantRole::SalesRep->value,
        ])->assertStatus(201);

        $token = $inviteResp->json('invitation.token');
        auth()->logout();
        session()->flush();

        // Wrong password via HTML form returns 302 redirect with session error (not JSON)
        $this->post("/invitations/{$token}/accept", [
            'password' => 'wrong-pass',
        ])->assertRedirect(route('invitations.show', ['token' => $token]))
            ->assertSessionHasErrors('password');

        // Same wrong password via AJAX JSON returns 403 JSON
        $this->postJson("/invitations/{$token}/accept", [
            'password' => 'wrong-pass',
        ])->assertStatus(403)->assertJsonPath('message', 'Invalid credentials for existing account.');

        // Wrong authenticated user via HTML form returns 302 with session error
        $this->actingAs($otherUser)
            ->post("/invitations/{$token}/accept")
            ->assertRedirect(route('invitations.show', ['token' => $token]))
            ->assertSessionHasErrors('email');

        // Wrong authenticated user via AJAX JSON returns 403 JSON
        $this->actingAs($otherUser)
            ->postJson("/invitations/{$token}/accept")
            ->assertStatus(403);

        auth()->logout();
        session()->flush();

        // Correct password via HTML form logs in, regenerates session, and redirects to dashboard
        $this->post("/invitations/{$token}/accept", [
            'password' => 'CorrectPass123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existingUser);
        $this->assertSame($org->id, session('active_organization_id'));

        // Already accepted invitation: HTML -> 302 + session error; JSON -> 422
        $this->post("/invitations/{$token}/accept")
            ->assertRedirect(route('invitations.show', ['token' => $token]))
            ->assertSessionHasErrors('invitation');

        $this->postJson("/invitations/{$token}/accept")
            ->assertStatus(422);

        // Expired invitation: HTML -> 302 + session error; JSON -> 410
        $expired = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)->create([
            'organization_id' => $org->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'expired-browser@acme.test',
            'role' => TenantRole::SalesRep->value,
            'token' => str_repeat('b', 64),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post("/invitations/{$expired->token}/accept", [
            'name' => 'Expired Browser',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertRedirect(route('invitations.show', ['token' => $expired->token]))
            ->assertSessionHasErrors('invitation');

        $this->postJson("/invitations/{$expired->token}/accept", [
            'name' => 'Expired Browser',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertStatus(410);
    }

    public function test_api_me_enforces_x_organization_id_header_when_provided(): void
    {
        ['organization' => $orgA, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $orgB] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        // User belongs to BOTH Org A and Org B, but token is strictly scoped to Org A
        $this->addUserToOrg($orgB, $admin, TenantRole::OrgAdmin->value);

        $this->actingAsOrgMember($admin, $orgA);
        $tokenResp = $this->postJson('/ajax/team/tokens', ['name' => 'Org A Integration'])->assertStatus(201);
        $plainText = $tokenResp->json('plain_text_token');
        $tokenId = $tokenResp->json('token.id');

        // Mismatched X-Organization-ID (even for another org the user belongs to) returns 403 and does not touch last_used_at
        $this->withHeaders([
            'Authorization' => "Bearer {$plainText}",
            'X-Organization-ID' => (string) $orgB->id,
        ])->getJson('/api/me')->assertStatus(403);

        $tokenModel = ApiToken::withoutGlobalScope(OrganizationScope::class)->findOrFail($tokenId);
        $this->assertNull($tokenModel->last_used_at);

        // Malformed X-Organization-ID headers return 403
        foreach (['abc', '0', '-5'] as $badHeader) {
            $this->withHeaders([
                'Authorization' => "Bearer {$plainText}",
                'X-Organization-ID' => $badHeader,
            ])->getJson('/api/me')->assertStatus(403);
        }

        // Omitted X-Organization-ID returns 200 for Org A
        $this->flushHeaders();
        $this->withHeaders([
            'Authorization' => "Bearer {$plainText}",
        ])->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('organization.id', $orgA->id);

        // Matching X-Organization-ID returns 200 for Org A and updates last_used_at
        $this->withHeaders([
            'Authorization' => "Bearer {$plainText}",
            'X-Organization-ID' => (string) $orgA->id,
        ])->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('organization.id', $orgA->id);

        $this->assertNotNull($tokenModel->fresh()->last_used_at);
    }
}
