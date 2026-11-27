<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Communications\WhatsApp\Models\WhatsAppTemplate;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadAssignment;
use App\Modules\Leads\Models\LeadStatusHistory;
use App\Modules\Notifications\Notifications\LeadAssignedNotification;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\CouponRedemption;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Models\RbacRole;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tenancy\Services\RbacService;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use App\Support\Security\DataEncrypter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Idempotent, encryption-safe development & local PRC verification seeder.
 *
 * Covers multi-tenant isolation, RBAC (system roles, custom role, per-user allow/deny
 * overrides, inviter tracking), subscriptions & coupons, B2B/B2C leads, tracking links,
 * multi-touch attribution, pipelines/deals (incl. stale SLA), WhatsApp coexistence,
 * notifications, audit logs, and GDPR/encryption flows.
 */
class DevVerificationSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Password@123';

    public const DEV_API_TOKEN = 'crm_dev_acme_admin_token_001';

    public function run(): void
    {
        if (SubscriptionPlan::query()->count() === 0) {
            $this->call(SubscriptionPlanSeeder::class);
        }

        app(RbacService::class)->ensureSeeded();

        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $orgs = $this->seedOrganizations();

            $this->seedMemberships($orgs, $users);
            $this->seedCustomRoleAndOverrides($orgs['acme'], $users);
            $this->seedSubscriptionsAndBilling($orgs, $users);
            $this->seedSecurityArtifacts($orgs['acme'], $users);
            $this->seedOrganizationSettings($orgs);

            $acmeCustomFields = $this->seedCustomFields($orgs['acme']);
            $acmePipelines = $this->seedAcmePipelines($orgs['acme']);
            $novaPipeline = $this->seedDefaultPipelineFor($orgs['nova'], 'D2C Direct Pipeline');
            $this->seedDefaultPipelineFor($orgs['beta'], 'Sandbox Pipeline');

            $acmeCampaigns = $this->seedAcmeCampaigns($orgs['acme'], $users['admin']);
            $novaCampaign = $this->seedNovaCampaign($orgs['nova'], $users['nova_admin']);

            $acmeTracking = $this->seedAcmeTrackingLinks($orgs['acme'], $acmeCampaigns, $acmeCustomFields, $users);
            $novaTracking = $this->seedNovaTrackingLink($orgs['nova'], $novaCampaign, $users['nova_admin']);

            $acmeCrm = $this->seedAcmePipelineAndLeads($orgs['acme'], $users, $acmePipelines, $acmeCampaigns, $acmeTracking);
            $this->seedNovaPipelineAndLeads($orgs['nova'], $users, $novaPipeline, $novaCampaign, $novaTracking);

            $this->seedAcmeFollowUpsAndActivities($orgs['acme'], $users, $acmeCrm);
            $this->seedAcmeWhatsApp($orgs['acme'], $acmeCrm);
            $this->seedNotificationsAndAuditLogs($orgs['acme'], $users, $acmeCrm);
        });

        app(RbacService::class)->flushCache();
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $passwordHash = Hash::make(self::DEFAULT_PASSWORD);

        $specs = [
            'superadmin' => ['name' => 'Aarav Platform Superadmin', 'email' => 'superadmin@crm.local', 'phone' => '+919800000001', 'is_superadmin' => true],
            'admin' => ['name' => 'Vikram Org Admin', 'email' => 'admin@acme.local', 'phone' => '+919800000002', 'is_superadmin' => false],
            'admin2' => ['name' => 'Meera Co-Admin', 'email' => 'admin2@acme.local', 'phone' => '+919800000003', 'is_superadmin' => false],
            'manager' => ['name' => 'Priya Sales Manager', 'email' => 'manager@acme.local', 'phone' => '+919800000004', 'is_superadmin' => false],
            'rep1' => ['name' => 'Rohan Sales Executive', 'email' => 'rep1@acme.local', 'phone' => '+919800000005', 'is_superadmin' => false],
            'rep2' => ['name' => 'Neha Sales Executive', 'email' => 'rep2@acme.local', 'phone' => '+919800000006', 'is_superadmin' => false],
            'viewer' => ['name' => 'Kabir ReadOnly Auditor', 'email' => 'viewer@acme.local', 'phone' => '+919800000007', 'is_superadmin' => false],
            'support' => ['name' => 'Ananya Support Agent', 'email' => 'support@acme.local', 'phone' => '+919800000008', 'is_superadmin' => false],
            'nova_admin' => ['name' => 'Siddharth Nova Admin', 'email' => 'nova-admin@nova.local', 'phone' => '+919800000009', 'is_superadmin' => false],
            'pending_admin' => ['name' => 'Devika Pending Admin', 'email' => 'pending@beta.local', 'phone' => '+919800000010', 'is_superadmin' => false],
        ];

        $users = [];
        foreach ($specs as $key => $spec) {
            $user = User::query()->whereBlindIndex('email_hash', $spec['email'], 'email')->first();

            if (! $user instanceof User) {
                $user = new User();
            }

            $user->forceFill([
                'name' => $spec['name'],
                'email' => $spec['email'],
                'phone' => $spec['phone'],
                'locale' => 'en_IN',
                'email_verified_at' => now()->subDays(10),
                'password' => $passwordHash,
                'is_superadmin' => $spec['is_superadmin'],
            ]);
            $user->save();

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, Organization>
     */
    private function seedOrganizations(): array
    {
        $specs = [
            'acme' => [
                'uuid' => '11111111-1111-4111-8111-111111111101',
                'name' => 'Acme Tech India',
                'slug' => 'acme-tech',
                'registration_identifier' => 'CIN-ACME-2026-001',
                'primary_contact_email' => 'ops@acme.local',
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'settings' => ['industry' => 'SaaS & B2B/B2C Commerce', 'demo_tenant' => true],
            ],
            'nova' => [
                'uuid' => '11111111-1111-4111-8111-111111111102',
                'name' => 'Nova Retail D2C',
                'slug' => 'nova-retail',
                'registration_identifier' => 'CIN-NOVA-2026-002',
                'primary_contact_email' => 'hello@nova.local',
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'settings' => ['industry' => 'Direct-to-Consumer Retail', 'whatsapp_optional' => true],
            ],
            'beta' => [
                'uuid' => '11111111-1111-4111-8111-111111111103',
                'name' => 'Beta Pending Sandbox',
                'slug' => 'beta-pending',
                'registration_identifier' => 'CIN-BETA-2026-003',
                'primary_contact_email' => 'billing@beta.local',
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'settings' => ['purpose' => 'Subscription paywall verification'],
            ],
            'gamma' => [
                'uuid' => '11111111-1111-4111-8111-111111111104',
                'name' => 'Gamma Suspended Corp',
                'slug' => 'gamma-suspended',
                'registration_identifier' => 'CIN-GAMMA-2026-004',
                'primary_contact_email' => 'admin@gamma.local',
                'status' => 'suspended',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'settings' => ['purpose' => 'Suspended tenant verification'],
            ],
        ];

        $orgs = [];
        foreach ($specs as $key => $spec) {
            $org = Organization::withTrashed()->where('slug', $spec['slug'])->first() ?? new Organization();
            $org->fill($spec);
            $org->deleted_at = null;
            $org->save();
            $orgs[$key] = $org;
        }

        return $orgs;
    }

    /**
     * @param  array<string, Organization>  $orgs
     * @param  array<string, User>  $users
     */
    private function seedMemberships(array $orgs, array $users): void
    {
        $acmeMembers = [
            [$users['superadmin'], TenantRole::OrgAdmin->value, null],
            [$users['admin'], TenantRole::OrgAdmin->value, null],
            [$users['admin2'], TenantRole::OrgAdmin->value, $users['superadmin']->id],
            [$users['manager'], TenantRole::SalesManager->value, $users['admin']->id],
            [$users['rep1'], TenantRole::SalesRep->value, $users['admin']->id],
            [$users['rep2'], TenantRole::SalesRep->value, $users['admin2']->id],
            [$users['viewer'], TenantRole::ReadOnly->value, $users['admin']->id],
            [$users['support'], 'support_agent', $users['admin']->id],
        ];

        foreach ($acmeMembers as [$member, $role, $inviterId]) {
            $this->upsertMembership($orgs['acme'], $member, $role, $inviterId);
        }

        // Multi-org role downgrade: admin@acme.local is org_admin in Acme, read_only in Nova
        $this->upsertMembership($orgs['nova'], $users['nova_admin'], TenantRole::OrgAdmin->value, null);
        $this->upsertMembership($orgs['nova'], $users['admin'], TenantRole::ReadOnly->value, $users['nova_admin']->id);

        $this->upsertMembership($orgs['beta'], $users['pending_admin'], TenantRole::OrgAdmin->value, null);
        $this->upsertMembership($orgs['gamma'], $users['superadmin'], TenantRole::OrgAdmin->value, null);
    }

    private function upsertMembership(Organization $org, User $user, string $role, ?int $invitedByUserId): void
    {
        DB::table('organization_user')->updateOrInsert(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'invited_by_user_id' => $invitedByUserId,
                'created_at' => now()->subDays(15),
                'updated_at' => now(),
            ]
        );
        $user->clearRoleCache($org);
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedCustomRoleAndOverrides(Organization $acme, array $users): void
    {
        $supportRole = RbacRole::query()->updateOrCreate(
            ['slug' => 'support_agent'],
            [
                'name' => 'Support Agent',
                'description' => 'Custom role for customer support & WhatsApp engagement.',
                'is_system' => false,
            ]
        );

        $supportSlugs = [
            'dashboard.view',
            'leads.view',
            'contacts.view',
            'accounts.view',
            'follow_ups.view',
            'custom_fields.view',
            'pipelines.view',
            'whatsapp.view',
            'whatsapp.send',
        ];
        $supportPermIds = RbacPermission::query()
            ->whereIn('slug', $supportSlugs)
            ->pluck('id')
            ->all();
        $supportRole->permissions()->sync($supportPermIds);

        // Per-user overrides for rep1@acme.local: allow reports.view, deny tracking_links.create
        $rbacService = app(RbacService::class);
        $reportsPerm = RbacPermission::query()->where('slug', 'reports.view')->first();
        $createTrackingPerm = RbacPermission::query()->where('slug', 'tracking_links.create')->first();

        if ($reportsPerm) {
            $rbacService->setUserOverride($acme, $users['rep1'], (int) $reportsPerm->id, true);
        }
        if ($createTrackingPerm) {
            $rbacService->setUserOverride($acme, $users['rep1'], (int) $createTrackingPerm->id, false);
        }
    }

    /**
     * @param  array<string, Organization>  $orgs
     * @param  array<string, User>  $users
     */
    private function seedSubscriptionsAndBilling(array $orgs, array $users): void
    {
        $goldPlan = SubscriptionPlan::query()->where('code', 'gold')->firstOrFail();
        $starterPlan = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        $welcomeCoupon = Coupon::query()->where('code', 'WELCOME20')->first();

        Coupon::query()->updateOrCreate(
            ['code' => 'FESTIVE500'],
            [
                'description' => 'Flat ₹500 off any subscription plan.',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount_paise' => null,
                'valid_from' => now()->subDays(5),
                'valid_until' => now()->addMonths(6),
                'max_redemptions' => 100,
                'applicable_plan_codes' => ['starter', 'gold'],
                'is_active' => true,
                'created_by' => $users['superadmin']->id,
            ]
        );

        Coupon::query()->updateOrCreate(
            ['code' => 'EXPIRED10'],
            [
                'description' => 'Expired promotional coupon for negative validation checks.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount_paise' => 50000,
                'valid_from' => now()->subMonths(3),
                'valid_until' => now()->subDays(5),
                'max_redemptions' => 50,
                'applicable_plan_codes' => ['starter', 'gold'],
                'is_active' => true,
                'created_by' => $users['superadmin']->id,
            ]
        );

        $acmeSub = Subscription::query()->updateOrCreate(
            ['razorpay_subscription_id' => 'sub_dev_acme_gold_001'],
            [
                'organization_id' => $orgs['acme']->id,
                'subscription_plan_id' => $goldPlan->id,
                'coupon_id' => $welcomeCoupon?->id,
                'status' => 'active',
                'razorpay_plan_id' => 'plan_dev_gold',
                'razorpay_customer_id' => 'cust_dev_acme',
                'base_amount_paise' => $goldPlan->price_paise,
                'discount_paise' => 99980,
                'initial_charge_paise' => $goldPlan->price_paise - 99980,
                'is_mandate_only' => false,
                'currency' => 'INR',
                'current_period_start' => now()->subDays(10),
                'current_period_end' => now()->addDays(20),
                'cancel_at_period_end' => false,
                'metadata' => ['seeded' => true],
            ]
        );

        if ($welcomeCoupon) {
            CouponRedemption::query()->updateOrCreate(
                ['coupon_id' => $welcomeCoupon->id, 'subscription_id' => $acmeSub->id],
                [
                    'organization_id' => $orgs['acme']->id,
                    'user_id' => $users['admin']->id,
                    'discount_paise' => 99980,
                    'redeemed_at' => now()->subDays(10),
                ]
            );
            $welcomeCoupon->forceFill(['redemptions_count' => CouponRedemption::query()->where('coupon_id', $welcomeCoupon->id)->count()])->save();
        }

        Subscription::query()->updateOrCreate(
            ['razorpay_subscription_id' => 'sub_dev_nova_starter_001'],
            [
                'organization_id' => $orgs['nova']->id,
                'subscription_plan_id' => $starterPlan->id,
                'status' => 'active',
                'razorpay_plan_id' => 'plan_dev_starter',
                'razorpay_customer_id' => 'cust_dev_nova',
                'base_amount_paise' => $starterPlan->price_paise,
                'discount_paise' => 0,
                'initial_charge_paise' => $starterPlan->price_paise,
                'is_mandate_only' => false,
                'currency' => 'INR',
                'current_period_start' => now()->subDays(5),
                'current_period_end' => now()->addDays(25),
                'cancel_at_period_end' => false,
            ]
        );

        Subscription::query()->updateOrCreate(
            ['razorpay_subscription_id' => 'sub_dev_beta_pending_001'],
            [
                'organization_id' => $orgs['beta']->id,
                'subscription_plan_id' => $starterPlan->id,
                'status' => 'pending_payment',
                'razorpay_plan_id' => 'plan_dev_starter',
                'razorpay_customer_id' => 'cust_dev_beta',
                'base_amount_paise' => $starterPlan->price_paise,
                'discount_paise' => 0,
                'initial_charge_paise' => $starterPlan->price_paise,
                'is_mandate_only' => false,
                'currency' => 'INR',
                'cancel_at_period_end' => false,
            ]
        );

        PaymentGatewayLog::query()->updateOrCreate(
            ['razorpay_event_id' => 'evt_dev_acme_activated_001'],
            [
                'organization_id' => $orgs['acme']->id,
                'subscription_id' => $acmeSub->id,
                'user_id' => $users['admin']->id,
                'direction' => 'inbound_webhook',
                'event_or_endpoint' => 'subscription.activated',
                'http_method' => 'POST',
                'http_status' => 200,
                'razorpay_subscription_id' => 'sub_dev_acme_gold_001',
                'razorpay_payment_id' => 'pay_dev_acme_001',
                'request_payload' => ['event' => 'subscription.activated'],
                'response_payload' => ['status' => 'ok'],
                'signature_valid' => true,
                'status' => 'success',
                'duration_ms' => 42,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(10),
            ]
        );
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedSecurityArtifacts(Organization $acme, array $users): void
    {
        $encrypter = DataEncrypter::instance();

        // Pending valid invitation
        $pendingToken = 'dev_pending_invite_token_acme_001';
        $pendingTokenHash = $encrypter->blindIndex($pendingToken, 'token');
        $pendingInv = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)
            ->where('token_hash', $pendingTokenHash)
            ->first() ?? new OrganizationInvitation();
        $pendingInv->fill([
            'organization_id' => $acme->id,
            'invited_by_user_id' => $users['admin']->id,
            'email' => 'pending-invite@acme.local',
            'role' => TenantRole::SalesRep->value,
            'token' => $pendingToken,
            'expires_at' => now()->addDays(5),
            'accepted_at' => null,
            'accepted_by_user_id' => null,
        ]);
        $pendingInv->save();

        // Expired invitation
        $expiredToken = 'dev_expired_invite_token_acme_001';
        $expiredTokenHash = $encrypter->blindIndex($expiredToken, 'token');
        $expiredInv = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)
            ->where('token_hash', $expiredTokenHash)
            ->first() ?? new OrganizationInvitation();
        $expiredInv->fill([
            'organization_id' => $acme->id,
            'invited_by_user_id' => $users['admin']->id,
            'email' => 'expired-invite@acme.local',
            'role' => TenantRole::ReadOnly->value,
            'token' => $expiredToken,
            'expires_at' => now()->subDays(2),
            'accepted_at' => null,
            'accepted_by_user_id' => null,
        ]);
        $expiredInv->save();

        // Active & Revoked API tokens
        ApiToken::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['token_hash' => hash('sha256', self::DEV_API_TOKEN)],
            [
                'organization_id' => $acme->id,
                'user_id' => $users['admin']->id,
                'name' => 'Dev Local Verification Token',
                'token_prefix' => substr(self::DEV_API_TOKEN, 0, 12),
                'abilities' => ['*'],
                'expires_at' => now()->addYear(),
                'revoked_at' => null,
            ]
        );

        ApiToken::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['token_hash' => hash('sha256', 'crm_dev_revoked_token_001')],
            [
                'organization_id' => $acme->id,
                'user_id' => $users['admin']->id,
                'name' => 'Revoked Legacy Token',
                'token_prefix' => 'crm_dev_revo',
                'abilities' => ['leads:read'],
                'expires_at' => now()->addYear(),
                'revoked_at' => now()->subDays(1),
            ]
        );
    }

    /**
     * @param  array<string, Organization>  $orgs
     */
    private function seedOrganizationSettings(array $orgs): void
    {
        OrganizationSetting::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $orgs['acme']->id],
            [
                'whatsapp_phone' => '+919999000100',
                'whatsapp_default_message' => 'Hello! Thanks for reaching out to Acme Tech India.',
                'duplicate_lead_strategy' => 'flag_and_link',
                'capture_ip_address' => true,
                'lead_reference_prefix' => 'ACME',
                'require_email' => true,
                'require_company' => false,
            ]
        );

        OrganizationSetting::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $orgs['nova']->id],
            [
                'whatsapp_phone' => null,
                'whatsapp_default_message' => 'Hi from Nova Retail!',
                'duplicate_lead_strategy' => 'merge',
                'capture_ip_address' => false,
                'lead_reference_prefix' => 'NOVA',
                'require_email' => false,
                'require_company' => false,
            ]
        );

        OrganizationSetting::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $orgs['beta']->id],
            [
                'whatsapp_phone' => null,
                'duplicate_lead_strategy' => 'allow',
                'capture_ip_address' => false,
                'lead_reference_prefix' => 'BETA',
                'require_email' => false,
                'require_company' => false,
            ]
        );
    }

    /**
     * @return array<string, CustomFieldDefinition>
     */
    private function seedCustomFields(Organization $acme): array
    {
        $defs = [
            'lead_city' => ['entity_type' => 'lead', 'field_key' => 'city_tier', 'label' => 'City Tier', 'data_type' => 'select', 'options' => ['Tier 1', 'Tier 2', 'Tier 3'], 'is_required' => false, 'order_index' => 1],
            'lead_budget' => ['entity_type' => 'lead', 'field_key' => 'monthly_budget', 'label' => 'Monthly Budget (INR)', 'data_type' => 'number', 'options' => null, 'is_required' => false, 'order_index' => 2],
            'lead_gst' => ['entity_type' => 'lead', 'field_key' => 'gst_verified', 'label' => 'GST Verified', 'data_type' => 'boolean', 'options' => null, 'is_required' => false, 'order_index' => 3],
            'lead_channels' => ['entity_type' => 'lead', 'field_key' => 'interested_modules', 'label' => 'Interested Modules', 'data_type' => 'multiselect', 'options' => ['CRM', 'WhatsApp', 'Attribution', 'Analytics'], 'is_required' => false, 'order_index' => 4],
            'contact_dob' => ['entity_type' => 'contact', 'field_key' => 'renewal_date', 'label' => 'Renewal Date', 'data_type' => 'date', 'options' => null, 'is_required' => false, 'order_index' => 1],
            'account_pan' => ['entity_type' => 'account', 'field_key' => 'billing_code', 'label' => 'Billing Reference', 'data_type' => 'text', 'options' => null, 'is_required' => false, 'order_index' => 1],
            'deal_notes' => ['entity_type' => 'deal', 'field_key' => 'implementation_notes', 'label' => 'Implementation Notes', 'data_type' => 'textarea', 'options' => null, 'is_required' => false, 'order_index' => 1],
        ];

        $result = [];
        foreach ($defs as $key => $def) {
            $result[$key] = CustomFieldDefinition::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
                [
                    'organization_id' => $acme->id,
                    'entity_type' => $def['entity_type'],
                    'field_key' => $def['field_key'],
                ],
                $def
            );
        }

        return $result;
    }

    /**
     * @return array{b2b: Pipeline, b2b_stages: array<string, PipelineStage>, b2c: Pipeline, b2c_stages: array<string, PipelineStage>}
     */
    private function seedAcmePipelines(Organization $acme): array
    {
        $b2b = Pipeline::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'name' => 'Enterprise B2B Pipeline'],
            ['is_default' => true]
        );
        $b2bStages = $this->seedStages($b2b, [
            'discovery' => ['name' => 'Discovery', 'order_index' => 1, 'win_probability' => 15, 'category' => 'open', 'stale_days' => 7],
            'qualified' => ['name' => 'Qualified', 'order_index' => 2, 'win_probability' => 40, 'category' => 'open', 'stale_days' => 10],
            'proposal' => ['name' => 'Proposal Sent', 'order_index' => 3, 'win_probability' => 70, 'category' => 'open', 'stale_days' => 7],
            'won' => ['name' => 'Closed Won', 'order_index' => 4, 'win_probability' => 100, 'category' => 'won', 'stale_days' => null],
            'lost' => ['name' => 'Closed Lost', 'order_index' => 5, 'win_probability' => 0, 'category' => 'lost', 'stale_days' => null],
        ]);

        $b2c = Pipeline::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'name' => 'B2C Fast Track'],
            ['is_default' => false]
        );
        $b2cStages = $this->seedStages($b2c, [
            'inquiry' => ['name' => 'Inquiry', 'order_index' => 1, 'win_probability' => 25, 'category' => 'open', 'stale_days' => 5],
            'checkout' => ['name' => 'Checkout Link Sent', 'order_index' => 2, 'win_probability' => 65, 'category' => 'open', 'stale_days' => 3],
            'won' => ['name' => 'Paid Won', 'order_index' => 3, 'win_probability' => 100, 'category' => 'won', 'stale_days' => null],
            'lost' => ['name' => 'Dropped Lost', 'order_index' => 4, 'win_probability' => 0, 'category' => 'lost', 'stale_days' => null],
        ]);

        return [
            'b2b' => $b2b,
            'b2b_stages' => $b2bStages,
            'b2c' => $b2c,
            'b2c_stages' => $b2cStages,
        ];
    }

    /**
     * @return array{pipeline: Pipeline, stages: array<string, PipelineStage>}
     */
    private function seedDefaultPipelineFor(Organization $org, string $name): array
    {
        $pipeline = Pipeline::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $org->id, 'name' => $name],
            ['is_default' => true]
        );

        $stages = $this->seedStages($pipeline, [
            'new' => ['name' => 'New', 'order_index' => 1, 'win_probability' => 15, 'category' => 'open', 'stale_days' => 10],
            'qualified' => ['name' => 'Qualified', 'order_index' => 2, 'win_probability' => 50, 'category' => 'open', 'stale_days' => 10],
            'won' => ['name' => 'Won', 'order_index' => 3, 'win_probability' => 100, 'category' => 'won', 'stale_days' => null],
            'lost' => ['name' => 'Lost', 'order_index' => 4, 'win_probability' => 0, 'category' => 'lost', 'stale_days' => null],
        ]);

        return ['pipeline' => $pipeline, 'stages' => $stages];
    }

    /**
     * @param  array<string, array{name: string, order_index: int, win_probability: int, category: string, stale_days: ?int}>  $specs
     * @return array<string, PipelineStage>
     */
    private function seedStages(Pipeline $pipeline, array $specs): array
    {
        $stages = [];
        foreach ($specs as $key => $spec) {
            $stages[$key] = PipelineStage::query()->updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'order_index' => $spec['order_index']],
                $spec
            );
        }

        return $stages;
    }

    /**
     * @return array<string, Campaign>
     */
    private function seedAcmeCampaigns(Organization $acme, User $creator): array
    {
        $specs = [
            'meta_ads' => ['name' => 'Q4 Meta Lead Gen Ads', 'description' => 'Paid Meta ads driving B2B demo captures.', 'type' => 'paid_ads', 'platform' => 'facebook', 'budget' => 75000, 'status' => 'active'],
            'whatsapp' => ['name' => 'WhatsApp Festive Broadcast', 'description' => 'Opt-in WhatsApp direct buyer inquiries.', 'type' => 'whatsapp', 'platform' => 'whatsapp', 'budget' => 25000, 'status' => 'active'],
            'linkedin' => ['name' => 'Enterprise LinkedIn Outreach', 'description' => 'Targeted B2B decision-maker campaign.', 'type' => 'social', 'platform' => 'linkedin', 'budget' => 50000, 'status' => 'active'],
        ];

        $campaigns = [];
        foreach ($specs as $key => $spec) {
            $campaigns[$key] = Campaign::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
                ['organization_id' => $acme->id, 'name' => $spec['name']],
                array_merge($spec, [
                    'starts_at' => now()->subDays(30)->toDateString(),
                    'ends_at' => now()->addDays(30)->toDateString(),
                    'created_by' => $creator->id,
                ])
            );
        }

        return $campaigns;
    }

    private function seedNovaCampaign(Organization $nova, User $creator): Campaign
    {
        return Campaign::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $nova->id, 'name' => 'Instagram D2C Winter Drop'],
            [
                'description' => 'Direct-to-consumer Instagram campaign (email/phone only, no WhatsApp).',
                'type' => 'paid_ads',
                'platform' => 'instagram',
                'budget' => 30000,
                'starts_at' => now()->subDays(15)->toDateString(),
                'ends_at' => now()->addDays(45)->toDateString(),
                'status' => 'active',
                'created_by' => $creator->id,
            ]
        );
    }

    /**
     * @param  array<string, Campaign>  $campaigns
     * @param  array<string, CustomFieldDefinition>  $customFields
     * @param  array<string, User>  $users
     * @return array<string, mixed>
     */
    private function seedAcmeTrackingLinks(Organization $acme, array $campaigns, array $customFields, array $users): array
    {
        $metaLink = TrackingLink::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['code' => 'acme-meta-q4'],
            [
                'organization_id' => $acme->id,
                'campaign_id' => $campaigns['meta_ads']->id,
                'slug' => 'acme-demo-q4',
                'name' => 'Meta Demo Capture Form',
                'destination_url' => 'https://acme.example.com/demo',
                'channel' => 'ad',
                'source' => 'meta',
                'medium' => 'cpc',
                'utm_source' => 'facebook',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'q4_b2b_demo',
                'ad_spend' => 45000,
                'capture_custom_field_ids' => [$customFields['lead_city']->id, $customFields['lead_budget']->id],
                'assigned_user_id' => $users['rep1']->id,
                'auto_assign_round_robin' => false,
                'is_active' => true,
            ]
        );

        $waLink = TrackingLink::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['code' => 'acme-wa-fest'],
            [
                'organization_id' => $acme->id,
                'campaign_id' => $campaigns['whatsapp']->id,
                'slug' => 'acme-wa-festive',
                'name' => 'WhatsApp Festive Click-to-Chat',
                'destination_url' => 'https://wa.me/919999000100',
                'channel' => 'whatsapp',
                'source' => 'whatsapp',
                'medium' => 'broadcast',
                'utm_source' => 'whatsapp',
                'utm_medium' => 'messaging',
                'utm_campaign' => 'festive_2026',
                'ad_spend' => 15000,
                'whatsapp_number' => '+919999000100',
                'whatsapp_message' => 'Hi Acme! Interested in your festive CRM bundle.',
                'assigned_user_id' => $users['rep2']->id,
                'auto_assign_round_robin' => true,
                'is_active' => true,
            ]
        );

        // Unconverted Visit-Only clicks (verifies P3: Zero False Conversion Tracking)
        $visitOnlyClick = LinkClick::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['click_token' => 'clk_dev_acme_visit_only_001'],
            [
                'organization_id' => $acme->id,
                'tracking_link_id' => $metaLink->id,
                'ip_hash' => hash('sha256', '192.0.2.10'),
                'visitor_hash' => hash('sha256', 'visitor-001'),
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X)',
                'device' => 'desktop',
                'browser' => 'Chrome',
                'referer' => 'https://facebook.com/',
                'landing_page' => 'https://acme.example.com/demo',
                'clicked_at' => now()->subDays(8),
            ]
        );

        $metaClick = LinkClick::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['click_token' => 'clk_dev_acme_b2b_won_001'],
            [
                'organization_id' => $acme->id,
                'tracking_link_id' => $metaLink->id,
                'ip_hash' => hash('sha256', '192.0.2.11'),
                'visitor_hash' => hash('sha256', 'visitor-002'),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
                'device' => 'desktop',
                'browser' => 'Edge',
                'referer' => 'https://instagram.com/',
                'landing_page' => 'https://acme.example.com/demo',
                'clicked_at' => now()->subDays(14),
            ]
        );

        $waClick = LinkClick::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['click_token' => 'clk_dev_acme_wa_won_002'],
            [
                'organization_id' => $acme->id,
                'tracking_link_id' => $waLink->id,
                'ip_hash' => hash('sha256', '192.0.2.12'),
                'visitor_hash' => hash('sha256', 'visitor-002'),
                'user_agent' => 'Mozilla/5.0 (iPhone)',
                'device' => 'mobile',
                'browser' => 'Safari',
                'referer' => 'https://wa.me/',
                'landing_page' => 'https://wa.me/919999000100',
                'clicked_at' => now()->subDays(12),
            ]
        );

        Touchpoint::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'link_click_id' => $visitOnlyClick->id, 'event_milestone' => 'click'],
            [
                'tracking_link_id' => $metaLink->id,
                'occurred_at' => $visitOnlyClick->clicked_at,
                'metadata' => ['note' => 'Visit only — no lead conversion'],
                'created_at' => $visitOnlyClick->clicked_at,
            ]
        );

        return [
            'meta_link' => $metaLink,
            'wa_link' => $waLink,
            'meta_click' => $metaClick,
            'wa_click' => $waClick,
        ];
    }

    private function seedNovaTrackingLink(Organization $nova, Campaign $campaign, User $owner): TrackingLink
    {
        return TrackingLink::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['code' => 'nova-ig-winter'],
            [
                'organization_id' => $nova->id,
                'campaign_id' => $campaign->id,
                'slug' => 'nova-winter-drop',
                'name' => 'Nova Winter Drop Form',
                'destination_url' => 'https://nova.example.com/winter',
                'channel' => 'social',
                'source' => 'instagram',
                'medium' => 'story',
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'utm_campaign' => 'winter_drop',
                'ad_spend' => 12000,
                'assigned_user_id' => $owner->id,
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, mixed>  $pipelines
     * @param  array<string, Campaign>  $campaigns
     * @param  array<string, mixed>  $tracking
     * @return array<string, mixed>
     */
    private function seedAcmePipelineAndLeads(
        Organization $acme,
        array $users,
        array $pipelines,
        array $campaigns,
        array $tracking,
    ): array {
        $b2bAccount = $this->upsertAccount($acme, 'zenith-logistics.in', [
            'name' => 'Zenith Logistics Pvt Ltd',
            'industry' => 'Supply Chain & Logistics',
            'annual_revenue' => 45000000,
            'employee_count' => 240,
            'billing_address' => ['city' => 'Bengaluru', 'state' => 'KA', 'country' => 'IN'],
            'custom_fields' => ['billing_code' => 'ZEN-KA-889'],
        ]);

        $b2bContact = $this->upsertContact($acme, 'anita.rao@zenith-logistics.in', [
            'account_id' => $b2bAccount->id,
            'first_name' => 'Anita',
            'last_name' => 'Rao',
            'phone' => '+919811112201',
            'job_title' => 'VP Operations',
            'preferred_channel' => 'whatsapp',
            'custom_fields' => ['renewal_date' => now()->addMonths(11)->toDateString()],
        ]);

        $b2cContact = $this->upsertContact($acme, 'kiran.menon@consumer.in', [
            'account_id' => null,
            'first_name' => 'Kiran',
            'last_name' => 'Menon',
            'phone' => '+919811112202',
            'job_title' => null,
            'preferred_channel' => 'whatsapp',
            'custom_fields' => ['renewal_date' => now()->addMonths(6)->toDateString()],
        ]);

        // Deals: 1 Won (multi-touch), 1 Open Stale (for SLA command), 1 Open B2C, 1 Lost with reason
        $wonDeal = Deal::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'title' => 'Zenith Annual Enterprise Suite'],
            [
                'pipeline_id' => $pipelines['b2b']->id,
                'pipeline_stage_id' => $pipelines['b2b_stages']['won']->id,
                'contact_id' => $b2bContact->id,
                'account_id' => $b2bAccount->id,
                'assigned_user_id' => $users['rep1']->id,
                'amount' => 180000,
                'currency' => 'INR',
                'expected_close_date' => now()->subDays(2)->toDateString(),
                'stage_entered_at' => now()->subDays(2),
                'closed_at' => now()->subDays(2),
                'is_stale' => false,
                'custom_fields' => ['implementation_notes' => 'Multi-branch onboarding approved.'],
            ]
        );

        $staleDeal = Deal::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'title' => 'Zenith Warehouse Add-on'],
            [
                'pipeline_id' => $pipelines['b2b']->id,
                'pipeline_stage_id' => $pipelines['b2b_stages']['proposal']->id,
                'contact_id' => $b2bContact->id,
                'account_id' => $b2bAccount->id,
                'assigned_user_id' => $users['rep1']->id,
                'amount' => 65000,
                'currency' => 'INR',
                'expected_close_date' => now()->addDays(15)->toDateString(),
                'stage_entered_at' => now()->subDays(20),
                'closed_at' => null,
                'is_stale' => true,
            ]
        );

        $openB2cDeal = Deal::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'title' => 'Kiran Annual Pro Plan'],
            [
                'pipeline_id' => $pipelines['b2c']->id,
                'pipeline_stage_id' => $pipelines['b2c_stages']['checkout']->id,
                'contact_id' => $b2cContact->id,
                'account_id' => null,
                'assigned_user_id' => $users['rep2']->id,
                'amount' => 14999,
                'currency' => 'INR',
                'expected_close_date' => now()->addDays(5)->toDateString(),
                'stage_entered_at' => now()->subDay(),
                'closed_at' => null,
                'is_stale' => false,
            ]
        );

        Deal::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'title' => 'Legacy Competitor Evaluation'],
            [
                'pipeline_id' => $pipelines['b2b']->id,
                'pipeline_stage_id' => $pipelines['b2b_stages']['lost']->id,
                'contact_id' => $b2bContact->id,
                'account_id' => $b2bAccount->id,
                'assigned_user_id' => $users['rep2']->id,
                'amount' => 50000,
                'currency' => 'INR',
                'expected_close_date' => now()->subDays(4)->toDateString(),
                'stage_entered_at' => now()->subDays(4),
                'closed_at' => now()->subDays(4),
                'lost_reason' => 'Budget frozen until next quarter',
                'is_stale' => false,
            ]
        );

        // Leads across B2B/B2C, statuses, and ownership (rep1, rep2, unassigned)
        $convertedB2bLead = Lead::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_reference' => 'ACME-2026-0001'],
            [
                'campaign_id' => $campaigns['meta_ads']->id,
                'tracking_link_id' => $tracking['meta_link']->id,
                'type' => 'B2B',
                'status' => 'converted',
                'priority' => 'high',
                'first_name' => 'Anita',
                'last_name' => 'Rao',
                'email' => 'anita.rao@zenith-logistics.in',
                'phone' => '+919811112201',
                'company_name' => 'Zenith Logistics Pvt Ltd',
                'job_title' => 'VP Operations',
                'employee_count' => 240,
                'location' => 'Bengaluru',
                'requirement' => 'Need multi-team CRM with WhatsApp tracking.',
                'source' => 'meta',
                'assigned_user_id' => $users['rep1']->id,
                'converted_contact_id' => $b2bContact->id,
                'converted_account_id' => $b2bAccount->id,
                'converted_deal_id' => $wonDeal->id,
                'converted_at' => now()->subDays(10),
                'custom_fields' => ['city_tier' => 'Tier 1', 'monthly_budget' => 15000, 'gst_verified' => true],
            ]
        );

        $qualifiedB2bLead = Lead::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_reference' => 'ACME-2026-0002'],
            [
                'campaign_id' => $campaigns['linkedin']->id,
                'type' => 'B2B',
                'status' => 'qualified',
                'priority' => 'high',
                'first_name' => 'Rajesh',
                'last_name' => 'Kulkarni',
                'email' => 'rajesh@apexmfg.in',
                'phone' => '+919811112203',
                'company_name' => 'Apex Manufacturing',
                'job_title' => 'Head of Sales',
                'employee_count' => 120,
                'location' => 'Pune',
                'requirement' => 'Evaluating B2B pipeline and custom fields.',
                'source' => 'linkedin',
                'assigned_user_id' => $users['rep1']->id,
                'custom_fields' => ['city_tier' => 'Tier 1', 'interested_modules' => ['CRM', 'Analytics']],
            ]
        );

        $contactedB2cLead = Lead::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_reference' => 'ACME-2026-0003'],
            [
                'campaign_id' => $campaigns['whatsapp']->id,
                'tracking_link_id' => $tracking['wa_link']->id,
                'type' => 'B2C',
                'status' => 'contacted',
                'priority' => 'medium',
                'first_name' => 'Sneha',
                'last_name' => 'Verma',
                'email' => 'sneha.verma@example.in',
                'phone' => '+919811112204',
                'location' => 'Jaipur',
                'requirement' => 'Interested in personal coaching package.',
                'source' => 'whatsapp',
                'assigned_user_id' => $users['rep2']->id,
                'custom_fields' => ['city_tier' => 'Tier 2'],
            ]
        );

        $unassignedLead = Lead::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_reference' => 'ACME-2026-0004'],
            [
                'campaign_id' => $campaigns['meta_ads']->id,
                'tracking_link_id' => $tracking['meta_link']->id,
                'type' => 'B2C',
                'status' => 'new',
                'priority' => 'medium',
                'first_name' => 'Arjun',
                'last_name' => 'Desai',
                'email' => 'arjun.desai@example.in',
                'phone' => '+919811112205',
                'location' => 'Ahmedabad',
                'requirement' => 'Requested callback from landing form.',
                'source' => 'website',
                'assigned_user_id' => null,
            ]
        );

        // Touchpoints for multi-touch attribution on the Won Deal (First-Touch Meta, Last-Touch WhatsApp)
        $touchpoints = [
            ['event_milestone' => 'click', 'tracking_link_id' => $tracking['meta_link']->id, 'link_click_id' => $tracking['meta_click']->id, 'lead_id' => $convertedB2bLead->id, 'contact_id' => $b2bContact->id, 'deal_id' => $wonDeal->id, 'occurred_at' => now()->subDays(14)],
            ['event_milestone' => 'lead_captured', 'tracking_link_id' => $tracking['meta_link']->id, 'link_click_id' => $tracking['meta_click']->id, 'lead_id' => $convertedB2bLead->id, 'contact_id' => $b2bContact->id, 'deal_id' => $wonDeal->id, 'occurred_at' => now()->subDays(13)],
            ['event_milestone' => 'conversation_qualified', 'tracking_link_id' => $tracking['wa_link']->id, 'link_click_id' => $tracking['wa_click']->id, 'lead_id' => $convertedB2bLead->id, 'contact_id' => $b2bContact->id, 'deal_id' => $wonDeal->id, 'occurred_at' => now()->subDays(11)],
            ['event_milestone' => 'deal_won', 'tracking_link_id' => $tracking['wa_link']->id, 'link_click_id' => $tracking['wa_click']->id, 'lead_id' => $convertedB2bLead->id, 'contact_id' => $b2bContact->id, 'deal_id' => $wonDeal->id, 'occurred_at' => now()->subDays(2)],
        ];

        foreach ($touchpoints as $tp) {
            Touchpoint::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
                [
                    'organization_id' => $acme->id,
                    'deal_id' => $tp['deal_id'],
                    'event_milestone' => $tp['event_milestone'],
                ],
                $tp + ['created_at' => $tp['occurred_at']]
            );
        }

        // Lead assignment & status history
        LeadAssignment::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_id' => $qualifiedB2bLead->id, 'assigned_to_user_id' => $users['rep1']->id],
            ['assigned_by_user_id' => $users['manager']->id, 'assigned_at' => now()->subDays(3), 'notes' => 'Assigned for B2B discovery']
        );

        LeadStatusHistory::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $acme->id, 'lead_id' => $qualifiedB2bLead->id, 'to_status' => 'qualified'],
            ['from_status' => 'new', 'changed_by_user_id' => $users['rep1']->id, 'reason' => 'Discovery call confirmed budget', 'changed_at' => now()->subDays(2)]
        );

        return [
            'b2b_account' => $b2bAccount,
            'b2b_contact' => $b2bContact,
            'b2c_contact' => $b2cContact,
            'won_deal' => $wonDeal,
            'stale_deal' => $staleDeal,
            'open_b2c_deal' => $openB2cDeal,
            'converted_lead' => $convertedB2bLead,
            'qualified_lead' => $qualifiedB2bLead,
            'contacted_lead' => $contactedB2cLead,
            'unassigned_lead' => $unassignedLead,
        ];
    }

    /**
     * @param  array<string, User>  $users
     * @param  array{pipeline: Pipeline, stages: array<string, PipelineStage>}  $pipelineData
     */
    private function seedNovaPipelineAndLeads(
        Organization $nova,
        array $users,
        array $pipelineData,
        Campaign $campaign,
        TrackingLink $trackingLink,
    ): void {
        $contact = $this->upsertContact($nova, 'diya.kapoor@nova-buyer.in', [
            'account_id' => null,
            'first_name' => 'Diya',
            'last_name' => 'Kapoor',
            'phone' => '+919822223301',
            'preferred_channel' => 'email',
        ]);

        $deal = Deal::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $nova->id, 'title' => 'Nova Winter Bundle Order'],
            [
                'pipeline_id' => $pipelineData['pipeline']->id,
                'pipeline_stage_id' => $pipelineData['stages']['won']->id,
                'contact_id' => $contact->id,
                'account_id' => null,
                'assigned_user_id' => $users['nova_admin']->id,
                'amount' => 4999,
                'currency' => 'INR',
                'expected_close_date' => now()->subDay()->toDateString(),
                'stage_entered_at' => now()->subDay(),
                'closed_at' => now()->subDay(),
            ]
        );

        Lead::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['organization_id' => $nova->id, 'lead_reference' => 'NOVA-2026-0001'],
            [
                'campaign_id' => $campaign->id,
                'tracking_link_id' => $trackingLink->id,
                'type' => 'B2C',
                'status' => 'converted',
                'priority' => 'medium',
                'first_name' => 'Diya',
                'last_name' => 'Kapoor',
                'email' => 'diya.kapoor@nova-buyer.in',
                'phone' => '+919822223301',
                'location' => 'Mumbai',
                'requirement' => 'D2C winter bundle inquiry (Email only)',
                'source' => 'instagram',
                'assigned_user_id' => $users['nova_admin']->id,
                'converted_contact_id' => $contact->id,
                'converted_deal_id' => $deal->id,
                'converted_at' => now()->subDay(),
            ]
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, mixed>  $crm
     */
    private function seedAcmeFollowUpsAndActivities(Organization $acme, array $users, array $crm): void
    {
        $followUps = [
            ['lead_id' => $crm['qualified_lead']->id, 'assigned_user_id' => $users['rep1']->id, 'type' => 'call', 'due_at' => now()->subHours(4), 'status' => 'pending', 'notes' => 'Overdue pricing follow-up'],
            ['lead_id' => $crm['contacted_lead']->id, 'assigned_user_id' => $users['rep2']->id, 'type' => 'whatsapp', 'due_at' => now()->addHours(2), 'status' => 'pending', 'notes' => 'Today demo confirmation'],
            ['lead_id' => $crm['qualified_lead']->id, 'assigned_user_id' => $users['rep1']->id, 'type' => 'meeting', 'due_at' => now()->addDays(2), 'status' => 'pending', 'notes' => 'Upcoming technical review'],
            ['lead_id' => $crm['converted_lead']->id, 'assigned_user_id' => $users['rep1']->id, 'type' => 'email', 'due_at' => now()->subDays(5), 'completed_at' => now()->subDays(5), 'status' => 'completed', 'notes' => 'Sent signed agreement', 'outcome' => 'Signed'],
        ];

        foreach ($followUps as $fu) {
            FollowUp::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
                [
                    'organization_id' => $acme->id,
                    'lead_id' => $fu['lead_id'],
                    'type' => $fu['type'],
                    'status' => $fu['status'],
                ],
                $fu + ['created_by_user_id' => $users['manager']->id]
            );
        }

        Activity::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            [
                'organization_id' => $acme->id,
                'activitable_type' => Deal::class,
                'activitable_id' => $crm['won_deal']->id,
                'action' => 'deal_won',
            ],
            [
                'user_id' => $users['rep1']->id,
                'description' => 'Closed-won annual enterprise contract after WhatsApp qualification.',
                'metadata' => ['amount' => 180000],
            ]
        );

        Activity::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            [
                'organization_id' => $acme->id,
                'activitable_type' => Lead::class,
                'activitable_id' => $crm['qualified_lead']->id,
                'action' => 'call_logged',
            ],
            [
                'user_id' => $users['rep1']->id,
                'description' => 'Spoke with Rajesh regarding 12-seat rollout.',
                'metadata' => ['duration_sec' => 420],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $crm
     */
    private function seedAcmeWhatsApp(Organization $acme, array $crm): void
    {
        $waAccount = WhatsAppAccount::withoutGlobalScope(OrganizationScope::class)->firstOrNew([
            'organization_id' => $acme->id,
        ]);
        $waAccount->fill([
            'waba_id' => 'waba_dev_acme_001',
            'phone_number_id' => 'pn_dev_acme_001',
            'display_phone_number' => '+919999000100',
            'verified_name' => 'Acme Tech India',
            'quality_rating' => 'GREEN',
            'webhook_verify_token' => 'dev_verify_token_acme',
            'is_coexistence_enabled' => true,
            'is_active' => true,
            'status' => 'connected',
        ]);
        $waAccount->setPlainAccessToken('dev_fake_meta_system_user_token');
        $waAccount->save();

        WhatsAppTemplate::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['whatsapp_account_id' => $waAccount->id, 'name' => 'welcome_utility', 'language' => 'en_IN'],
            [
                'organization_id' => $acme->id,
                'category' => 'UTILITY',
                'header_type' => 'TEXT',
                'body_text' => 'Hello {{1}}, thank you for contacting Acme Tech India.',
                'parameter_placeholders' => ['customer_name'],
                'meta_status' => 'APPROVED',
            ]
        );

        WhatsAppTemplate::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['whatsapp_account_id' => $waAccount->id, 'name' => 'promo_offer', 'language' => 'en_IN'],
            [
                'organization_id' => $acme->id,
                'category' => 'MARKETING',
                'header_type' => 'NONE',
                'body_text' => 'Hi {{1}}, unlock 20% off your annual upgrade!',
                'parameter_placeholders' => ['customer_name'],
                'meta_status' => 'PENDING',
            ]
        );

        // Active 24h conversation (Anita Rao)
        $activeConv = $this->upsertWaConversation($acme, $waAccount, $crm['b2b_contact'], '+919811112201', now()->subHours(2));
        WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['meta_message_id' => 'wamid.dev.acme.in.001'],
            [
                'organization_id' => $acme->id,
                'whatsapp_conversation_id' => $activeConv->id,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'Hi team, can you share the enterprise onboarding checklist?',
                'status' => 'read',
                'is_coexistence_echo' => false,
                'sent_at' => now()->subHours(2),
            ]
        );
        WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['meta_message_id' => 'wamid.dev.acme.echo.002'],
            [
                'organization_id' => $acme->id,
                'whatsapp_conversation_id' => $activeConv->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => 'Shared! Sent via WhatsApp Business mobile coexistence echo.',
                'status' => 'delivered',
                'is_coexistence_echo' => true,
                'sent_at' => now()->subHour(),
            ]
        );

        // Expired >24h conversation (Kiran Menon — requires template message)
        $expiredConv = $this->upsertWaConversation($acme, $waAccount, $crm['b2c_contact'], '+919811112202', now()->subHours(36));
        WhatsAppMessage::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
            ['meta_message_id' => 'wamid.dev.acme.in.003'],
            [
                'organization_id' => $acme->id,
                'whatsapp_conversation_id' => $expiredConv->id,
                'direction' => 'inbound',
                'message_type' => 'text',
                'body' => 'STOP',
                'status' => 'read',
                'is_coexistence_echo' => false,
                'sent_at' => now()->subHours(36),
            ]
        );

        // Opted-in & Opted-out consents
        $this->upsertWaConsent($acme, $crm['b2b_contact'], '+919811112201', true, 'web_form_checkbox');
        $this->upsertWaConsent($acme, $crm['b2c_contact'], '+919811112202', false, 'inbound_stop_keyword');
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, mixed>  $crm
     */
    private function seedNotificationsAndAuditLogs(Organization $acme, array $users, array $crm): void
    {
        $notifId = '22222222-2222-4222-8222-222222222201';
        $payload = (new LeadAssignedNotification($crm['qualified_lead']))->toDatabase($users['rep1']);

        DB::table('notifications')->updateOrInsert(
            ['id' => $notifId],
            [
                'type' => LeadAssignedNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $users['rep1']->id,
                'data' => json_encode($payload, JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ]
        );

        $auditSpecs = [
            ['action' => 'rbac.user_override.updated', 'auditable_type' => User::class, 'auditable_id' => $users['rep1']->id, 'user_id' => $users['superadmin']->id, 'new_values' => ['reports.view' => true, 'tracking_links.create' => false]],
            ['action' => 'lead.converted', 'auditable_type' => Lead::class, 'auditable_id' => $crm['converted_lead']->id, 'user_id' => $users['rep1']->id, 'new_values' => ['deal_id' => $crm['won_deal']->id]],
        ];

        foreach ($auditSpecs as $spec) {
            AuditLog::withoutGlobalScope(OrganizationScope::class)->updateOrCreate(
                [
                    'organization_id' => $acme->id,
                    'action' => $spec['action'],
                    'auditable_type' => $spec['auditable_type'],
                    'auditable_id' => $spec['auditable_id'],
                ],
                [
                    'user_id' => $spec['user_id'],
                    'new_values' => $spec['new_values'],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'DevVerificationSeeder/1.0',
                    'created_at' => now()->subDays(2),
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertAccount(Organization $org, string $domain, array $attributes): Account
    {
        $account = Account::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $org->id)
            ->get()
            ->first(static fn (Account $a): bool => $a->domain === $domain)
            ?? new Account();

        $account->fill(array_merge($attributes, [
            'organization_id' => $org->id,
            'domain' => $domain,
        ]));
        $account->save();

        return $account;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertContact(Organization $org, string $email, array $attributes): Contact
    {
        $contact = Contact::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $org->id)
            ->whereBlindIndex('email_hash', $email, 'email')
            ->first()
            ?? new Contact();

        $contact->fill(array_merge($attributes, [
            'organization_id' => $org->id,
            'email' => $email,
        ]));
        $contact->save();

        return $contact;
    }

    private function upsertWaConversation(
        Organization $org,
        WhatsAppAccount $account,
        Contact $contact,
        string $phone,
        \DateTimeInterface $lastInboundAt,
    ): WhatsAppConversation {
        $conv = WhatsAppConversation::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $org->id)
            ->whereBlindIndex('customer_phone_hash', $phone, 'phone')
            ->first()
            ?? new WhatsAppConversation();

        $conv->fill([
            'organization_id' => $org->id,
            'whatsapp_account_id' => $account->id,
            'contact_id' => $contact->id,
            'customer_phone' => $phone,
            'last_inbound_at' => $lastInboundAt,
            'last_message_at' => $lastInboundAt,
        ]);
        $conv->save();

        return $conv;
    }

    private function upsertWaConsent(
        Organization $org,
        Contact $contact,
        string $phone,
        bool $optedIn,
        string $source,
    ): void {
        $consent = WhatsAppConsent::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $org->id)
            ->whereBlindIndex('phone_hash', $phone, 'phone')
            ->first()
            ?? new WhatsAppConsent();

        $consent->fill([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'phone' => $phone,
            'is_opted_in' => $optedIn,
            'consent_source' => $source,
            'consent_timestamp' => now()->subDays(5),
            'ip_address' => '127.0.0.1',
        ]);
        $consent->save();
    }
}
