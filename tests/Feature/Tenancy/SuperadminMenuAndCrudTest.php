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
            ->assertDontSee('href="'.route('accounts.index').'"', false)
            ->assertDontSee('href="'.route('contacts.index').'"', false)
            ->assertDontSee('href="'.route('organizations.index').'"', false)
            ->assertDontSee('href="'.route('pipelines.index').'"', false)
            ->assertDontSee('href="'.route('whatsapp.chat').'"', false)
            ->assertDontSee('href="'.route('whatsapp.settings').'"', false)
            ->assertDontSee('href="'.route('audit-logs.index').'"', false);
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
            ->assertSee('href="'.route('whatsapp.chat').'"', false)
            ->assertSee('href="'.route('whatsapp.settings').'"', false)
            ->assertSee('href="'.route('audit-logs.index').'"', false);
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
