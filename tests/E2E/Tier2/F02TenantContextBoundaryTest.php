<?php

namespace Tests\E2E\Tier2;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\E2E\Support\E2ETestCase;

class F02TenantContextBoundaryTest extends E2ETestCase
{
    public function test_t2_f02_01_unauthenticated_request_is_redirected_or_rejected(): void
    {
        Auth::logout();
        $this->clearTenantHeader()->flushSession();

        $webRes = $this->get('/leads');
        $webRes->assertStatus(302);
        $webRes->assertRedirect('/login');

        $ajaxRes = $this->getJson('/ajax/leads');
        $ajaxRes->assertStatus(401);
    }

    public function test_t2_f02_02_x_organization_id_for_non_member_org_returns_403(): void
    {
        $tenantA = $this->registerOrganizationAndAdmin(['organization_name' => 'Member Org A']);
        $userA = $tenantA['user'];

        $tenantB = $this->registerOrganizationAndAdmin(['organization_name' => 'Foreign Org B']);
        $orgB = $tenantB['organization'];

        $response = $this->actingAs($userA)
            ->getJson('/ajax/leads', $this->tenantHeaders($orgB->id));

        $response->assertStatus(403);
    }

    public function test_t2_f02_03_nonexistent_or_malformed_x_organization_id_is_rejected(): void
    {
        $user = $this->registerOrganizationAndAdmin()['user'];

        $missingRes = $this->actingAs($user)
            ->getJson('/ajax/custom-fields', ['X-Organization-ID' => '999999']);
        $missingRes->assertStatus(403);

        $malformedRes = $this->actingAs($user)
            ->getJson('/ajax/custom-fields', ['X-Organization-ID' => 'abc']);
        $malformedRes->assertStatus(403);
    }

    public function test_t2_f02_04_user_with_zero_memberships_cannot_access_tenant_routes(): void
    {
        $orphanUser = User::create([
            'name' => 'Orphan User',
            'email' => 'orphan@example.test',
            'password' => 'Password123!',
        ]);

        $this->clearTenantHeader()->actingAs($orphanUser)->flushSession();

        $webRes = $this->get('/leads');
        $this->assertStatusIn($webRes, [302, 403]);
        if ($webRes->getStatusCode() === 302) {
            $this->assertStringContainsString('/organizations', (string) $webRes->headers->get('Location'));
        }

        $ajaxRes = $this->getJson('/ajax/leads');
        $ajaxRes->assertStatus(403);
    }

    public function test_t2_f02_05_stale_session_active_organization_id_after_removal_returns_403(): void
    {
        $tenant = $this->registerOrganizationAndAdmin(['organization_name' => 'Revoke Session Org']);
        $org = $tenant['organization'];

        $member = $this->createMemberInOrganization($org->id, 'sales_rep')['user'];
        $org->users()->detach($member->id);

        $response = $this->clearTenantHeader()
            ->actingAs($member)
            ->withSession(['active_organization_id' => $org->id])
            ->getJson('/ajax/leads');

        $response->assertStatus(403);
    }
}
