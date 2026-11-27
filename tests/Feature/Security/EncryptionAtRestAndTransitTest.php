<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Support\Security\DataEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\WithTenancyHelpers;
use Tests\TestCase;

class EncryptionAtRestAndTransitTest extends TestCase
{
    use RefreshDatabase;
    use WithTenancyHelpers;

    public function test_pii_and_secrets_are_encrypted_at_rest_and_transparently_decrypted(): void
    {
        ['organization' => $org, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        $lead = Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2B',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@example.com',
            'phone' => '+919876543210',
            'company_name' => 'Acme India Pvt Ltd',
            'location' => 'Bengaluru',
            'requirement' => 'Enterprise CRM rollout for 50 seats',
            'source' => 'manual',
            'status' => 'new',
            'custom_fields' => ['gstin' => '29ABCDE1234F1Z5'],
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'email' => 'priya@acme.in',
            'phone' => '+919811122233',
            'job_title' => 'VP Sales',
            'custom_fields' => ['pan' => 'ABCDE1234F'],
        ]);

        $account = Account::create([
            'organization_id' => $org->id,
            'name' => 'Acme Corp',
            'domain' => 'acme.in',
            'billing_address' => ['city' => 'Mumbai', 'pin' => '400001'],
            'custom_fields' => ['tier' => 'enterprise'],
        ]);

        $waAccount = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'waba_id' => 'waba_123',
            'phone_number_id' => 'pn_123',
            'display_phone_number' => '+919800000001',
            'verified_name' => 'Acme Support',
            'access_token_encrypted' => 'dummy',
            'webhook_verify_token' => 'verify_secret_123',
            'is_active' => true,
            'status' => 'connected',
        ]);
        $waAccount->setPlainAccessToken('meta_live_secret_token');
        $waAccount->save();

        $conversation = WhatsAppConversation::create([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $waAccount->id,
            'contact_id' => $contact->id,
            'customer_phone' => '+919811122233',
        ]);

        $message = WhatsAppMessage::create([
            'organization_id' => $org->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Confidential pricing inquiry',
            'status' => 'delivered',
            'error_payload' => ['detail' => 'none'],
        ]);

        $consent = WhatsAppConsent::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'phone' => '+919811122233',
            'is_opted_in' => true,
            'consent_source' => 'web_form',
            'consent_timestamp' => now(),
            'ip_address' => '203.0.113.42',
        ]);

        $pgLog = PaymentGatewayLog::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'direction' => 'webhook',
            'event_or_endpoint' => 'subscription.charged',
            'request_headers' => ['X-Razorpay-Signature' => 'sig_abc'],
            'request_payload' => ['amount' => 49900, 'email' => 'billing@acme.in'],
            'response_payload' => ['ok' => true],
            'ip_address' => '198.51.100.10',
            'created_at' => now(),
        ]);

        $audit = AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'action' => 'lead.updated',
            'auditable_type' => Lead::class,
            'auditable_id' => $lead->id,
            'old_values' => ['email' => 'old@acme.in'],
            'new_values' => ['email' => 'aarav@example.com'],
            'ip_address' => '198.51.100.99',
            'user_agent' => 'Mozilla/5.0 TestAgent',
            'created_at' => now(),
        ]);

        // Raw DB rows must NOT contain plaintext PII or secrets
        $rawUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($user->email, $rawUser->email);
        $this->assertNotEmpty($rawUser->email_hash);

        $rawLead = DB::table('leads')->where('id', $lead->id)->first();
        $this->assertStringNotContainsString('Aarav', (string) $rawLead->first_name);
        $this->assertStringNotContainsString('aarav@example.com', (string) $rawLead->email);
        $this->assertStringNotContainsString('9876543210', (string) $rawLead->phone);
        $this->assertStringNotContainsString('29ABCDE1234F1Z5', (string) $rawLead->custom_fields);
        $this->assertNotEmpty($rawLead->email_hash);
        $this->assertNotEmpty($rawLead->phone_hash);

        $rawContact = DB::table('contacts')->where('id', $contact->id)->first();
        $this->assertStringNotContainsString('Priya', (string) $rawContact->first_name);
        $this->assertStringNotContainsString('priya@acme.in', (string) $rawContact->email);

        $rawAccount = DB::table('accounts')->where('id', $account->id)->first();
        $this->assertStringNotContainsString('Acme Corp', (string) $rawAccount->name);
        $this->assertStringNotContainsString('400001', (string) $rawAccount->billing_address);

        $rawWa = DB::table('whatsapp_accounts')->where('id', $waAccount->id)->first();
        $this->assertStringNotContainsString('verify_secret_123', (string) $rawWa->webhook_verify_token);
        $this->assertStringNotContainsString('meta_live_secret_token', (string) $rawWa->access_token_encrypted);

        $rawMsg = DB::table('whatsapp_messages')->where('id', $message->id)->first();
        $this->assertStringNotContainsString('Confidential pricing inquiry', (string) $rawMsg->body);

        $rawConsent = DB::table('whatsapp_consents')->where('id', $consent->id)->first();
        $this->assertStringNotContainsString('203.0.113.42', (string) $rawConsent->ip_address);

        $rawPg = DB::table('payment_gateway_logs')->where('id', $pgLog->id)->first();
        $this->assertStringNotContainsString('billing@acme.in', (string) $rawPg->request_payload);

        $rawAudit = DB::table('audit_logs')->where('id', $audit->id)->first();
        $this->assertStringNotContainsString('198.51.100.99', (string) $rawAudit->ip_address);
        $this->assertStringNotContainsString('aarav@example.com', (string) $rawAudit->new_values);

        // Eloquent models transparently decrypt everything
        $freshLead = Lead::findOrFail($lead->id);
        $this->assertSame('Aarav', $freshLead->first_name);
        $this->assertSame('aarav@example.com', $freshLead->email);
        $this->assertSame(['gstin' => '29ABCDE1234F1Z5'], $freshLead->custom_fields);

        $freshWa = WhatsAppAccount::findOrFail($waAccount->id);
        $this->assertSame('meta_live_secret_token', $freshWa->getPlainAccessToken());
        $this->assertSame('verify_secret_123', $freshWa->webhook_verify_token);

        $freshMsg = WhatsAppMessage::findOrFail($message->id);
        $this->assertSame('Confidential pricing inquiry', $freshMsg->body);
    }

    public function test_blind_index_auth_invitation_and_lead_search_work_with_encrypted_columns(): void
    {
        ['organization' => $org, 'user' => $admin] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        // Auth::attempt uses EncryptedEloquentUserProvider + email_hash
        $this->assertTrue(Auth::attempt(['email' => $admin->email, 'password' => 'password']));
        Auth::logout();

        // Invitation lookup via token_hash
        $plainToken = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'invited_by_user_id' => $admin->id,
            'email' => 'invitee@example.com',
            'role' => TenantRole::SalesRep->value,
            'token' => $plainToken,
            'expires_at' => now()->addDay(),
        ]);

        $rawInv = DB::table('organization_invitations')->where('id', $invitation->id)->first();
        $this->assertStringNotContainsString($plainToken, (string) $rawInv->token);
        $this->assertNotEmpty($rawInv->token_hash);

        $this->get(route('invitations.show', ['token' => $plainToken]))
            ->assertOk();

        // Lead search via LeadController API on encrypted fields
        Lead::create([
            'organization_id' => $org->id,
            'type' => 'B2C',
            'first_name' => 'Zara',
            'last_name' => 'Khan',
            'email' => 'zara.khan@example.com',
            'phone' => '+919900112233',
            'source' => 'manual',
            'status' => 'new',
        ]);

        $response = $this->actingAsOrgMember($admin, $org)
            ->getJson(route('ajax.leads.index', ['search' => 'zara.khan']))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zara', $response->json('data.0.first_name'));
    }

    public function test_backfill_and_key_rotation_commands_encrypt_and_rotate_records(): void
    {
        ['organization' => $org, 'user' => $user] = $this->createOrgWithUser(TenantRole::OrgAdmin->value);
        $this->withTenant($org);

        // Insert a legacy plaintext row directly via DB::table
        $legacyId = DB::table('leads')->insertGetId([
            'organization_id' => $org->id,
            'type' => 'B2C',
            'status' => 'new',
            'first_name' => 'Legacy',
            'last_name' => 'Plaintext',
            'email' => 'legacy@example.com',
            'phone' => '+919800001234',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('crm:encrypt-existing-data')->assertExitCode(0);

        $rawAfterBackfill = DB::table('leads')->where('id', $legacyId)->first();
        $this->assertStringNotContainsString('legacy@example.com', (string) $rawAfterBackfill->email);
        $this->assertNotEmpty($rawAfterBackfill->email_hash);
        $this->assertNotEmpty($rawAfterBackfill->phone_hash);
        $this->assertSame('Legacy', Lead::findOrFail($legacyId)->first_name);

        $originalUserEmail = $user->email;

        // Rotate encryption key
        $oldKey = (string) (config('security.data_encryption_key') ?: config('app.key'));
        $newKey = 'base64:' . base64_encode(random_bytes(32));

        config([
            'security.data_encryption_key' => $newKey,
            'security.data_encryption_previous_keys' => [$oldKey],
        ]);
        app()->forgetInstance(DataEncrypter::class);
        app()->singleton(DataEncrypter::class);

        $this->artisan('crm:rotate-encryption-keys')->assertExitCode(0);

        // Verify decryption succeeds even after removing oldKey from previous_keys
        config([
            'security.data_encryption_key' => $newKey,
            'security.data_encryption_previous_keys' => [],
        ]);
        app()->forgetInstance(DataEncrypter::class);
        app()->singleton(DataEncrypter::class);

        $rotatedLead = Lead::findOrFail($legacyId);
        $this->assertSame('Legacy', $rotatedLead->first_name);
        $this->assertSame('legacy@example.com', $rotatedLead->email);
        $this->assertSame($originalUserEmail, User::findOrFail($user->id)->email);
    }

    public function test_transport_security_middleware_enforces_https_and_hsts_when_enabled(): void
    {
        config(['security.force_https' => true]);

        // Unencrypted GET redirects to HTTPS 301
        $this->get('/login')
            ->assertStatus(301);

        // Unencrypted POST is rejected with 426 Upgrade Required
        $this->postJson('/api/webhooks/razorpay', [])
            ->assertStatus(426);

        // HTTPS GET succeeds with HSTS & security headers
        $this->get('https://localhost/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
