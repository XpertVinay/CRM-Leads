<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminMenuAndCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createOrgWithMember(User $user, string $role = 'org_admin'): Organization
    {
        $org = Organization::create([
            'name' => 'Acme India',
            'slug' => 'acme-india-'.uniqid(),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'status' => 'active',
        ]);

        $org->users()->attach($user->id, ['role' => $role]);

        return $org;
    }

    public function test_sidebar_hides_superadmin_menu_items_from_non_superadmin(): void
    {
        $admin = User::factory()->create(['is_superadmin' => false]);
        $org = $this->createOrgWithMember($admin, 'org_admin');

        $response = $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard');

        $response->assertOk()
            ->assertSee('href="'.route('accounts.index').'"', false)
            ->assertSee('href="'.route('contacts.index').'"', false)
            ->assertSee('href="'.route('pipelines.index').'"', false)
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false)
            ->assertSee('href="'.route('whatsapp.settings').'"', false)
            ->assertSee('href="'.route('audit-logs.index').'"', false)
            ->assertDontSee('href="'.route('organizations.index').'"', false)
            ->assertDontSee('href="'.route('superadmin.rbac.index').'"', false)
            ->assertDontSee('href="'.route('superadmin.menus.index').'"', false)
            ->assertDontSee('href="'.route('coupons.index').'"', false);
    }

    public function test_superadmin_can_crud_roles_permissions_and_portal_menus(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $admin = User::factory()->create(['is_superadmin' => false]);
        $org = $this->createOrgWithMember($admin, 'org_admin');

        // Non-superadmin gets 403 on menu CRUD
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->get(route('superadmin.menus.index'))
            ->assertStatus(403);

        // Superadmin can create, update, and delete a custom role
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->post(route('superadmin.rbac.roles.store'), [
                'name' => 'Support Agent',
                'slug' => 'support_agent',
                'description' => 'Handles customer support',
            ])
            ->assertRedirect(route('superadmin.rbac.index'));

        $role = \App\Modules\Tenancy\Models\RbacRole::query()->where('slug', 'support_agent')->firstOrFail();
        $this->delete(route('superadmin.rbac.roles.destroy', $role))
            ->assertRedirect(route('superadmin.rbac.index'));
        $this->assertDatabaseMissing('rbac_roles', ['id' => $role->id]);

        // Superadmin can create, update, and delete a portal menu
        $this->post(route('superadmin.menus.store'), [
            'label' => 'Custom Portal Link',
            'route_name' => 'dashboard',
            'route_pattern' => 'dashboard',
            'icon' => 'star',
            'permission_slug' => 'dashboard.view',
            'section' => 'main',
            'requires_org' => 1,
            'is_active' => 1,
            'sort_order' => 999,
        ])->assertRedirect(route('superadmin.menus.index'));

        $menu = \App\Modules\Tenancy\Models\RbacMenu::query()->where('label', 'Custom Portal Link')->firstOrFail();
        $this->patch(route('superadmin.menus.update', $menu), [
            'label' => 'Updated Portal Link',
            'route_name' => 'dashboard',
            'route_pattern' => 'dashboard',
            'icon' => 'star',
            'permission_slug' => 'dashboard.view',
            'section' => 'main',
            'requires_org' => 1,
            'is_active' => 1,
            'sort_order' => 999,
        ])->assertRedirect(route('superadmin.menus.index'));

        $this->assertSame('Updated Portal Link', $menu->fresh()->label);
        $this->delete(route('superadmin.menus.destroy', $menu))->assertRedirect(route('superadmin.menus.index'));
        $this->assertDatabaseMissing('rbac_menus', ['id' => $menu->id]);
    }

    public function test_sidebar_shows_all_six_menu_items_to_superadmin(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $org = $this->createOrgWithMember($superadmin, 'org_admin');

        $response = $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard');

        $response->assertOk()
            ->assertSee('href="'.route('accounts.index').'"', false)
            ->assertSee('href="'.route('contacts.index').'"', false)
            ->assertSee('href="'.route('organizations.index').'"', false)
            ->assertSee('href="'.route('pipelines.index').'"', false)
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false)
            ->assertSee('href="'.route('whatsapp.settings').'"', false)
            ->assertSee('href="'.route('audit-logs.index').'"', false)
            ->assertSee('href="'.route('coupons.index').'"', false);

        WhatsAppAccount::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_menu_test',
            'phone_number_id' => 'pn_menu_test',
            'display_phone_number' => '+919876540001',
            'access_token_encrypted' => encrypt('token'),
            'is_active' => true,
            'status' => 'connected',
        ]);

        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('whatsapp.chat').'"', false);
    }

    public function test_whatsapp_chat_menu_is_hidden_until_org_admin_configures_whatsapp_api(): void
    {
        $admin = User::factory()->create(['is_superadmin' => false]);
        $rep = User::factory()->create(['is_superadmin' => false]);
        $org = $this->createOrgWithMember($admin, 'org_admin');
        $org->users()->attach($rep->id, ['role' => 'sales_rep']);

        // Hidden before WhatsApp API integration is configured
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false)
            ->assertSee('href="'.route('whatsapp.settings').'"', false);

        $this->actingAs($rep)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false);

        // Direct access to WhatsApp Chat and chat AJAX endpoints is also forbidden before setup
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/whatsapp/chat')
            ->assertStatus(403);
        $this->getJson('/ajax/whatsapp/conversations')->assertStatus(403);

        $this->actingAs($rep)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/whatsapp/chat')
            ->assertStatus(403);

        // Connect WhatsApp API as org admin -> visible and accessible for admin and rep
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->postJson('/ajax/whatsapp/connect', [
                'waba_id' => 'waba_vis_001',
                'phone_number_id' => 'pn_vis_001',
                'display_phone_number' => '+919876543219',
                'access_token' => 'EAAG_vis_token',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('whatsapp.chat').'"', false);
        $this->get('/whatsapp/chat')->assertOk();

        $this->actingAs($rep)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('whatsapp.chat').'"', false);
        $this->get('/whatsapp/chat')->assertOk();

        // Deny whatsapp.view for rep -> menu hidden and route forbidden even when connected
        $waViewPerm = \App\Modules\Tenancy\Models\RbacPermission::query()->where('slug', 'whatsapp.view')->firstOrFail();
        app(\App\Modules\Tenancy\Services\RbacService::class)->setUserOverride($org, $rep, $waViewPerm->id, false);
        $this->actingAs($rep)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false);
        $this->get('/whatsapp/chat')->assertStatus(403);

        // Disconnect WhatsApp API -> hidden and forbidden again
        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->deleteJson('/ajax/whatsapp/disconnect')
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false);
        $this->get('/whatsapp/chat')->assertStatus(403);
    }

    public function test_non_superadmin_cannot_access_superadmin_crud_endpoints(): void
    {
        $admin = User::factory()->create(['is_superadmin' => false]);
        $org = $this->createOrgWithMember($admin, 'org_admin');

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->patchJson('/superadmin/organizations/'.$org->id, [
                'name' => 'Hack',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'status' => 'active',
            ])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson('/ajax/audit-logs', ['action' => 'test'])
            ->assertStatus(403);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson('/ajax/whatsapp/templates', [
                'name' => 'tpl',
                'language' => 'en_IN',
                'category' => 'utility',
                'body_text' => 'Hi',
            ])
            ->assertStatus(403);
    }

    public function test_superadmin_can_perform_crud_across_all_six_menu_pages(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $org = $this->createOrgWithMember($superadmin, 'org_admin');
        $otherOrg = Organization::create([
            'name' => 'Beta Corp',
            'slug' => 'beta-corp-'.uniqid(),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'status' => 'active',
        ]);

        // 1. Organizations CRUD
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->patchJson('/superadmin/organizations/'.$otherOrg->id, [
                'name' => 'Beta Corp Updated',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'status' => 'suspended',
            ])
            ->assertOk();
        $this->assertSame('Beta Corp Updated', $otherOrg->fresh()->name);

        $this->actingAs($superadmin)
            ->deleteJson('/superadmin/organizations/'.$otherOrg->id)
            ->assertOk();
        $this->assertSoftDeleted('organizations', ['id' => $otherOrg->id]);

        // 2. Pipelines CRUD
        $pipelineRes = $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson('/ajax/pipelines', [
                'name' => 'Sales Pipeline',
                'stages' => [['name' => 'New', 'category' => 'open']],
            ])
            ->assertStatus(201);
        $pipelineId = (int) $pipelineRes->json('data.id');

        $this->patchJson('/ajax/pipelines/'.$pipelineId, ['name' => 'Updated Pipeline'])->assertOk();
        $this->assertSame('Updated Pipeline', Pipeline::find($pipelineId)?->name);
        $this->deleteJson('/ajax/pipelines/'.$pipelineId)->assertOk();

        // 3. Accounts CRUD
        $accRes = $this->postJson('/ajax/accounts', ['name' => 'Reliance B2B'])->assertStatus(201);
        $accId = (int) $accRes->json('data.id');
        $this->patchJson('/ajax/accounts/'.$accId, ['name' => 'Reliance Enterprise'])->assertOk();
        $this->assertSame('Reliance Enterprise', Account::find($accId)?->name);
        $this->deleteJson('/ajax/accounts/'.$accId)->assertOk();

        // 4. Contacts CRUD
        $contactRes = $this->postJson('/ajax/contacts', [
            'first_name' => 'Aarav',
            'last_name' => 'Mehta',
            'email' => 'aarav@example.com',
        ])->assertStatus(201);
        $contactId = (int) $contactRes->json('data.id');
        $this->patchJson('/ajax/contacts/'.$contactId, ['first_name' => 'Aarav Updated'])->assertOk();
        $this->assertSame('Aarav Updated', Contact::find($contactId)?->first_name);
        $this->deleteJson('/ajax/contacts/'.$contactId)->assertOk();

        // 5. WhatsApp API Templates CRUD
        WhatsAppAccount::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_123',
            'phone_number_id' => 'pn_123',
            'display_phone_number' => '+919876543210',
            'access_token_encrypted' => encrypt('token'),
            'is_active' => true,
            'status' => 'connected',
        ]);
        $tplRes = $this->postJson('/ajax/whatsapp/templates', [
            'name' => 'welcome_in',
            'language' => 'en_IN',
            'category' => 'utility',
            'body_text' => 'Namaste {{1}}',
        ])->assertStatus(201);
        $tplId = (int) $tplRes->json('data.id');
        $this->patchJson('/ajax/whatsapp/templates/'.$tplId, ['body_text' => 'Namaste Ji'])->assertOk();
        $this->deleteJson('/ajax/whatsapp/templates/'.$tplId)->assertOk();

        // 6. Audit Logs CRUD
        $logRes = $this->postJson('/ajax/audit-logs', [
            'action' => 'superadmin_check',
            'auditable_type' => 'Organization',
            'auditable_id' => $org->id,
        ])->assertStatus(201);
        $logId = (int) $logRes->json('data.id');
        $this->patchJson('/ajax/audit-logs/'.$logId, ['action' => 'superadmin_verified'])->assertOk();
        $this->assertSame('superadmin_verified', AuditLog::find($logId)?->action);
        $this->deleteJson('/ajax/audit-logs/'.$logId)->assertOk();
        $this->assertDatabaseMissing('audit_logs', ['id' => $logId]);
    }
}
