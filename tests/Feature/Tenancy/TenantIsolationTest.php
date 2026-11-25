<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\OrganizationProvisionedEvent;
use App\Modules\Tenancy\Livewire\OrganizationSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_guest_and_orgless_user_cannot_access_tenant_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->getJson('/ajax/custom-fields')->assertStatus(401);

        $orglessUser = User::factory()->create();
        $this->actingAs($orglessUser)->get('/dashboard')->assertStatus(403);
    }

    public function test_registration_login_and_logout_manage_tenant_provisioning_and_context(): void
    {
        Event::fake([OrganizationProvisionedEvent::class]);

        $response = $this->post('/register', [
            'name' => 'Alice Admin',
            'email' => 'alice@acme.test',
            'phone' => '+15551234567',
            'organization_name' => 'Acme Holdings',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'alice@acme.test')->firstOrFail();
        $org = Organization::query()->where('name', 'Acme Holdings')->firstOrFail();

        $this->assertSame(TenantRole::OrgAdmin->value, $user->roleIn($org));
        $this->assertSame($org->id, session('active_organization_id'));
        Event::assertDispatched(OrganizationProvisionedEvent::class);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse(app(TenantContext::class)->hasTenant());

        $this->post('/login', [
            'email' => 'alice@acme.test',
            'password' => 'SecretPass123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame($org->id, session('active_organization_id'));
    }

    public function test_cross_tenant_http_and_route_model_binding_isolation(): void
    {
        ['organization' => $org1, 'user' => $admin1] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $org2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);

        $this->withTenant($org2);
        $org2Field = CustomFieldDefinition::create([
            'entity_type' => 'lead',
            'field_key' => 'secret_field',
            'label' => 'Secret Field',
            'data_type' => 'text',
        ]);
        app(TenantContext::class)->clear();

        $this->actingAsOrgMember($admin1, $org1);

        // Listing returns empty (Org 2 record not visible)
        $this->getJson('/ajax/custom-fields')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        // Route-model binding returns 404 for Org 2 record
        $this->patchJson("/ajax/custom-fields/{$org2Field->id}", ['label' => 'Tampered'])
            ->assertStatus(404);
        $this->deleteJson("/ajax/custom-fields/{$org2Field->id}")
            ->assertStatus(404);

        // X-Organization-ID header for non-member org returns 403
        $this->withHeaders(['X-Organization-ID' => (string) $org2->id])
            ->getJson('/ajax/custom-fields')
            ->assertStatus(403);
        $this->flushHeaders();

        // Switching to non-member org via AJAX returns 403
        $this->postJson('/ajax/organizations/switch', ['organization_id' => $org2->id])
            ->assertStatus(403);
    }

    public function test_suspended_organization_is_blocked_and_multi_org_switching_works(): void
    {
        ['organization' => $org1, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        ['organization' => $org2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->addUserToOrg($org2, $user, TenantRole::SalesRep->value);

        $this->actingAsOrgMember($user, $org1);

        // Switch via AJAX to Org 2
        $this->postJson('/ajax/organizations/switch', ['organization_id' => $org2->id])
            ->assertOk()
            ->assertJsonPath('organization.id', $org2->id)
            ->assertJsonPath('organization.role', TenantRole::SalesRep->value);

        // Switch via X-Organization-ID header back to Org 1
        $this->withHeaders(['X-Organization-ID' => (string) $org1->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($org1->name);
        $this->flushHeaders();

        // Livewire OrganizationSwitcher updates session & TenantContext
        Livewire::actingAs($user)
            ->test(OrganizationSwitcher::class)
            ->call('switchOrganization', $org2->id)
            ->assertRedirect(route('dashboard'));

        $this->assertSame($org2->id, session('active_organization_id'));
        $this->assertSame($org2->id, app(TenantContext::class)->getTenantId());

        // Suspending Org 2 immediately blocks requests for Org 2
        $org2->update(['status' => 'suspended']);
        $this->actingAs($user)
            ->withSession(['active_organization_id' => $org2->id])
            ->get('/dashboard')
            ->assertStatus(403);
    }

    public function test_organization_store_normalizes_slug_and_rejects_collisions_and_punctuation_only_slugs(): void
    {
        ['organization' => $existingOrg, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value, [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $this->actingAsOrgMember($user, $existingOrg);

        // Normalized slug collision returns 422 (never 500)
        $this->postJson('/organizations', [
            'name' => 'Another Org',
            'slug' => 'Acme Corp!',
        ])->assertStatus(422)->assertJsonValidationErrors('slug');

        // Punctuation-only slug returns 422
        $this->postJson('/organizations', [
            'name' => 'Another Org',
            'slug' => '!!!',
        ])->assertStatus(422)->assertJsonValidationErrors('slug');

        // Blank/whitespace slug auto-generates unique slug
        $created = $this->postJson('/organizations', [
            'name' => 'Acme Corp',
            'slug' => '   ',
        ])->assertStatus(201);

        $this->assertSame('acme-corp-2', $created->json('organization.slug'));
    }

    public function test_register_and_login_normalize_mixed_case_and_whitespace_emails(): void
    {
        $this->post('/register', [
            'name' => 'Case Admin',
            'email' => '  AdminCase@Example.COM  ',
            'organization_name' => 'Case Org',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'admincase@example.com']);

        $this->post('/logout')->assertRedirect(route('login'));

        // Duplicate registration with mixed-case email fails validation cleanly
        $this->post('/register', [
            'name' => 'Duplicate Case Admin',
            'email' => 'ADMINCASE@example.com',
            'organization_name' => 'Duplicate Case Org',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'admincase@example.com')->count());

        // Login with uppercase + surrounding whitespace succeeds
        $this->post('/login', [
            'email' => '  ADMINCASE@example.com  ',
            'password' => 'SecretPass123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_register_supports_optional_organization_provisioning_fields_and_defaults(): void
    {
        // Existing org claims ops@custom.com and REG-100
        $this->createOrgWithUser(TenantRole::OrgAdmin->value, [
            'name' => 'Prior Org',
            'slug' => 'prior-org',
            'registration_identifier' => 'REG-099',
            'primary_contact_email' => 'taken@example.com',
        ]);

        // Registration WITH optional org fields
        $this->post('/register', [
            'name' => 'Custom Provisioner',
            'email' => 'provisioner@example.com',
            'organization_name' => 'Custom Org',
            'organization_slug' => '  My Custom Org!  ',
            'registration_identifier' => '  REG-100  ',
            'primary_contact_email' => '  Ops@Custom.COM  ',
            'timezone' => 'America/New_York',
            'currency' => 'eur',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertRedirect(route('dashboard'));

        $customOrg = Organization::query()->where('slug', 'my-custom-org')->firstOrFail();
        $this->assertSame('REG-100', $customOrg->registration_identifier);
        $this->assertSame('ops@custom.com', $customOrg->primary_contact_email);
        $this->assertSame('America/New_York', $customOrg->timezone);
        $this->assertSame('EUR', $customOrg->currency);

        $this->post('/logout')->assertRedirect(route('login'));

        // Registration WITHOUT optional fields uses defaults and leaves primary_contact_email null
        // (even if the user email matches another org's primary_contact_email)
        $this->post('/register', [
            'name' => 'Default Provisioner',
            'email' => 'taken@example.com',
            'organization_name' => 'Default Org',
            'organization_slug' => '   ',
            'registration_identifier' => '',
            'primary_contact_email' => '   ',
            'timezone' => '',
            'currency' => '',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertRedirect(route('dashboard'));

        $defaultOrg = Organization::query()->where('name', 'Default Org')->firstOrFail();
        $this->assertSame('default-org', $defaultOrg->slug);
        $this->assertNull($defaultOrg->registration_identifier);
        $this->assertNull($defaultOrg->primary_contact_email);
        $this->assertSame('UTC', $defaultOrg->timezone);
        $this->assertSame('USD', $defaultOrg->currency);
    }

    public function test_organization_provisioning_and_update_validate_timezone_currency_and_uniqueness(): void
    {
        ['organization' => $org1, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value, [
            'name' => 'Existing Org',
            'slug' => 'existing-org',
            'registration_identifier' => 'REG-EXIST',
            'primary_contact_email' => 'contact@existing.test',
        ]);

        ['organization' => $org2] = $this->createOrgWithUser(TenantRole::OrgAdmin->value, [
            'name' => 'Other Org',
            'slug' => 'other-org',
            'registration_identifier' => 'REG-OTHER',
            'primary_contact_email' => 'other@existing.test',
        ]);

        // Register validation (JSON) rejects invalid timezone, invalid currency, punctuation slug, and duplicates
        $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'new-reg@example.com',
            'organization_name' => 'New Org',
            'organization_slug' => '---',
            'registration_identifier' => 'REG-EXIST',
            'primary_contact_email' => '  CONTACT@existing.test  ',
            'timezone' => 'Invalid/Zone',
            'currency' => 'USDD',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'organization_slug',
            'registration_identifier',
            'primary_contact_email',
            'timezone',
            'currency',
        ]);

        $this->postJson('/register', [
            'name' => 'Test User',
            'email' => 'new-reg2@example.com',
            'organization_name' => 'New Org 2',
            'organization_slug' => 'Existing Org!',
            'currency' => '123',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'organization_slug',
            'currency',
        ]);

        // OrganizationController::store validation
        $this->actingAsOrgMember($admin, $org1);

        $this->postJson('/organizations', [
            'name' => 'Bad Store Org',
            'registration_identifier' => 'REG-OTHER',
            'primary_contact_email' => '  OTHER@existing.test ',
            'timezone' => 'Invalid/Zone',
            'currency' => 'U$D',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'registration_identifier',
            'primary_contact_email',
            'timezone',
            'currency',
        ]);

        // OrganizationController::update validation
        $this->patchJson('/organizations/settings', [
            'name' => 'Existing Org',
            'registration_identifier' => 'REG-OTHER',
            'primary_contact_email' => '  OTHER@existing.test ',
            'timezone' => 'Invalid/Zone',
            'currency' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'registration_identifier',
            'primary_contact_email',
            'timezone',
            'currency',
        ]);

        // Valid update with own existing unique values & valid timezone/currency succeeds
        $this->patchJson('/organizations/settings', [
            'name' => 'Existing Org Updated',
            'registration_identifier' => 'REG-EXIST',
            'primary_contact_email' => '  CONTACT@existing.test ',
            'timezone' => 'Europe/London',
            'currency' => 'gbp',
        ])->assertOk()->assertJsonPath('organization.currency', 'GBP');

        $this->assertSame('Europe/London', $org1->fresh()->timezone);
        $this->assertSame('contact@existing.test', $org1->fresh()->primary_contact_email);
    }
}
