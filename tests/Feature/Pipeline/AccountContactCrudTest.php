<?php

namespace Tests\Feature\Pipeline;

use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class AccountContactCrudTest extends TestCase
{
    use RefreshDatabase, WithTenancyHelpers;

    public function test_account_crud_and_tenant_isolation(): void
    {
        ['organization' => $orgA, 'user' => $adminA] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $orgB, 'user' => $adminB] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $this->actingAsOrgMember($adminA, $orgA)
            ->get(route('accounts.index'))
            ->assertOk();

        $createResp = $this->actingAsOrgMember($adminA, $orgA)
            ->postJson(route('ajax.accounts.store'), [
                'name' => 'Acme Corp',
                'domain' => 'acme.test',
                'industry' => 'SaaS',
                'annual_revenue' => 250000,
                'employee_count' => 45,
            ])
            ->assertCreated();

        $accountId = $createResp->json('data.id');
        $this->assertNotNull($accountId);

        $this->actingAsOrgMember($adminA, $orgA)
            ->getJson(route('ajax.accounts.index', ['search' => 'acme']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAsOrgMember($adminA, $orgA)
            ->patchJson(route('ajax.accounts.update', $accountId), [
                'industry' => 'Fintech',
            ])
            ->assertOk()
            ->assertJsonPath('data.industry', 'Fintech');

        // Org B cannot access Org A's account
        $this->actingAsOrgMember($adminB, $orgB)
            ->getJson(route('ajax.accounts.show', $accountId))
            ->assertNotFound();

        $this->actingAsOrgMember($adminA, $orgA)
            ->deleteJson(route('ajax.accounts.destroy', $accountId))
            ->assertOk();

        $this->assertSoftDeleted('accounts', ['id' => $accountId]);
    }

    public function test_contact_crud_and_b2b_account_linking(): void
    {
        ['organization' => $org, 'user' => $rep] = $this->createOrgWithUser(TenantRole::SalesRep->value);

        $this->withTenant($org);
        $account = Account::create([
            'organization_id' => $org->id,
            'name' => 'Globex Inc',
        ]);

        $this->actingAsOrgMember($rep, $org)
            ->get(route('contacts.index'))
            ->assertOk();

        $createResp = $this->actingAsOrgMember($rep, $org)
            ->postJson(route('ajax.contacts.store'), [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@globex.test',
                'phone' => '+15551234567',
                'account_id' => $account->id,
                'preferred_channel' => 'whatsapp',
            ])
            ->assertCreated();

        $contactId = $createResp->json('data.id');

        $this->actingAsOrgMember($rep, $org)
            ->get(route('contacts.show', $contactId))
            ->assertOk()
            ->assertSee('Jane Doe');

        // Read-only user cannot create or delete contacts
        ['organization' => $org, 'user' => $reader] = [
            'organization' => $org,
            'user' => \App\Models\User::factory()->create(),
        ];
        $this->addUserToOrg($org, $reader, TenantRole::ReadOnly->value);

        $this->actingAsOrgMember($reader, $org)
            ->postJson(route('ajax.contacts.store'), [
                'first_name' => 'Blocked',
            ])
            ->assertForbidden();

        // SalesRep cannot delete (only OrgAdmin or SalesManager)
        $this->actingAsOrgMember($rep, $org)
            ->deleteJson(route('ajax.contacts.destroy', $contactId))
            ->assertForbidden();

        $contact = Contact::withoutGlobalScopes()->find($contactId);
        $this->assertSame($account->id, $contact->account_id);
    }
}
