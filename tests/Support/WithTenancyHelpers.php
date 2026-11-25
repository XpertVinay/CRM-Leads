<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Services\TenantProvisioningService;

trait WithTenancyHelpers
{
    /**
     * @return array{organization: Organization, user: User}
     */
    protected function createOrgWithUser(
        string $role = TenantRole::OrgAdmin->value,
        array $orgAttributes = [],
        array $userAttributes = [],
    ): array {
        $user = User::factory()->create($userAttributes);

        if ($role === TenantRole::OrgAdmin->value) {
            $organization = app(TenantProvisioningService::class)->provisionOrganization($user, array_merge([
                'name' => 'Org '.uniqid(),
            ], $orgAttributes));
        } else {
            $owner = User::factory()->create();
            $organization = app(TenantProvisioningService::class)->provisionOrganization($owner, array_merge([
                'name' => 'Org '.uniqid(),
            ], $orgAttributes));
            $organization->users()->attach($user->id, ['role' => $role]);
        }

        return [
            'organization' => $organization,
            'user' => $user,
        ];
    }

    protected function addUserToOrg(Organization $organization, User $user, string $role): void
    {
        $organization->users()->attach($user->id, ['role' => $role]);
    }

    protected function withTenant(?Organization $organization): void
    {
        app(TenantContext::class)->setTenant($organization);
    }

    protected function actingAsOrgMember(User $user, Organization $organization): static
    {
        $this->withTenant($organization);

        return $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id]);
    }
}
