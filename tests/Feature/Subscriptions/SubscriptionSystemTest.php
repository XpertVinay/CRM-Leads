<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id' => 'rzp_test_key123',
            'services.razorpay.key_secret' => 'rzp_test_secret456',
            'services.razorpay.webhook_secret' => 'whsec_test_789',
            'services.razorpay.base_url' => 'https://api.razorpay.com/v1',
        ]);

        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_plans_are_loaded_from_database_and_cache_refreshes_on_update(): void
    {
        $plans = SubscriptionPlan::activeCached();
        $this->assertCount(2, $plans);
        $this->assertSame('starter', $plans[0]->code);
        $this->assertSame(249900, $plans[0]->price_paise);
        $this->assertSame('gold', $plans[1]->code);
        $this->assertSame(499900, $plans[1]->price_paise);

        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        $starter->update(['price_paise' => 279900]);

        $refreshed = SubscriptionPlan::activeCached();
        $this->assertSame(279900, $refreshed->firstWhere('code', 'starter')->price_paise);
    }

    public function test_register_page_renders_stepped_registration_with_coupon_redemption(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="reg-step-1"', false)
            ->assertSee('id="reg-step-2"', false)
            ->assertSee('id="reg-next-step-btn"', false)
            ->assertSee('id="coupon_code"', false)
            ->assertSee('Registration Details')
            ->assertSee('Subscription &amp; Coupon', false);
    }

    public function test_org_admin_registration_with_coupon_creates_pending_subscription_notifies_admin_and_gates_dashboard(): void
    {
        $gold = SubscriptionPlan::query()->where('code', 'gold')->firstOrFail();

        $response = $this->post('/register', [
            'name' => 'Priya Sharma',
            'email' => 'priya@acme.in',
            'phone' => '+919876543210',
            'organization_name' => 'Acme India',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'subscription_plan_id' => $gold->id,
            'coupon_code' => 'WELCOME20',
        ]);

        $response->assertRedirect(route('subscription.index', ['checkout' => 1]));

        $user = User::query()->whereBlindIndex('email_hash', 'priya@acme.in', 'email')->firstOrFail();
        $subscription = Subscription::query()->where('subscription_plan_id', $gold->id)->firstOrFail();

        $this->assertSame('pending_payment', $subscription->status);
        $this->assertSame(499900, $subscription->base_amount_paise);
        $this->assertSame(99980, $subscription->discount_paise);
        $this->assertSame(399920, $subscription->initial_charge_paise);
        $this->assertFalse($subscription->is_mandate_only);

        // Org Admin was notified at registration with plan details.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
        ]);

        // Dashboard is gated until subscription payment is verified.
        $this->get('/dashboard')->assertRedirect(route('subscription.index'));
    }

    public function test_superadmin_bypasses_subscription_gate_even_with_pending_payment_workspace(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $org = Organization::create([
            'name' => 'Pending Org',
            'slug' => 'pending-org-' . uniqid(),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'status' => 'active',
        ]);
        $org->users()->attach($superadmin->id, ['role' => 'org_admin']);

        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        Subscription::query()->create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'pending_payment',
            'base_amount_paise' => $starter->price_paise,
            'discount_paise' => 0,
            'initial_charge_paise' => $starter->price_paise,
            'currency' => 'INR',
        ]);

        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get(route('coupons.index'))
            ->assertOk();
    }

    public function test_hundred_percent_discount_coupon_sets_one_rupee_mandate_and_razorpay_checkout_activates_subscription(): void
    {
        Coupon::query()->create([
            'code' => 'FREE100',
            'description' => '100% off first month',
            'discount_type' => 'percentage',
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();

        $this->post('/register', [
            'name' => 'Rahul Verma',
            'email' => 'rahul@mandate.in',
            'organization_name' => 'Mandate Corp',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'subscription_plan_id' => $starter->id,
            'coupon_code' => 'FREE100',
        ])->assertRedirect(route('subscription.index', ['checkout' => 1]));

        $subscription = Subscription::query()->latest('id')->firstOrFail();
        $this->assertSame(249900, $subscription->discount_paise);
        $this->assertSame(100, $subscription->initial_charge_paise);
        $this->assertTrue($subscription->is_mandate_only);

        Http::fake([
            'https://api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_rzp_starter'], 200),
            'https://api.razorpay.com/v1/subscriptions' => Http::response([
                'id' => 'sub_rzp_12345',
                'status' => 'created',
            ], 200),
        ]);

        $checkoutRes = $this->postJson(route('ajax.subscription.checkout'), [
            'subscription_plan_id' => $starter->id,
            'coupon_code' => 'FREE100',
        ]);

        $checkoutRes->assertOk()
            ->assertJsonPath('subscription_id', 'sub_rzp_12345')
            ->assertJsonPath('amount_paise', 100)
            ->assertJsonPath('is_mandate_only', true);

        // Verify outbound API request & response were audited in payment_gateway_logs.
        $this->assertDatabaseHas('payment_gateway_logs', [
            'direction' => 'outbound_api',
            'event_or_endpoint' => 'POST /subscriptions',
            'razorpay_subscription_id' => 'sub_rzp_12345',
            'status' => 'success',
        ]);

        // Verify checkout callback signature.
        $paymentId = 'pay_rzp_999';
        $signature = hash_hmac('sha256', "{$paymentId}|sub_rzp_12345", 'rzp_test_secret456');

        $verifyRes = $this->postJson(route('ajax.subscription.verify'), [
            'local_subscription_id' => $subscription->id,
            'razorpay_payment_id' => $paymentId,
            'razorpay_subscription_id' => 'sub_rzp_12345',
            'razorpay_signature' => $signature,
        ]);

        $verifyRes->assertOk()->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('payment_gateway_logs', [
            'direction' => 'checkout_callback',
            'razorpay_payment_id' => $paymentId,
            'signature_valid' => true,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('coupon_redemptions', [
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
        ]);

        // Dashboard is now unlocked.
        $this->get('/dashboard')->assertOk();
    }

    public function test_razorpay_api_exception_is_logged_in_payment_gateway_logs(): void
    {
        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();

        $this->post('/register', [
            'name' => 'Error Tester',
            'email' => 'err@test.in',
            'organization_name' => 'Error Org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'subscription_plan_id' => $starter->id,
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/plans' => Http::response([
                'error' => ['description' => 'Invalid API key'],
            ], 401),
        ]);

        $this->postJson(route('ajax.subscription.checkout'), [
            'subscription_plan_id' => $starter->id,
        ])->assertStatus(502);

        $this->assertDatabaseHas('payment_gateway_logs', [
            'direction' => 'outbound_api',
            'event_or_endpoint' => 'POST /plans',
            'http_status' => 401,
            'status' => 'failed',
            'error_message' => 'Invalid API key',
        ]);
    }

    public function test_razorpay_client_uses_guzzle_compatible_tls_options(): void
    {
        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();

        $this->post('/register', [
            'name' => 'TLS Tester',
            'email' => 'tls@test.in',
            'organization_name' => 'TLS Org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'subscription_plan_id' => $starter->id,
        ])->assertRedirect(route('subscription.index', ['checkout' => 1]));

        $capturedOptions = [];
        Http::fake(function ($request, array $options) use (&$capturedOptions) {
            $capturedOptions[] = $options;

            $curlFactory = new \GuzzleHttp\Handler\CurlFactory(1);
            $easy = $curlFactory->create($request->toPsrRequest(), $options);
            $curlFactory->release($easy);
            $curlFactory->close();

            return str_ends_with($request->url(), '/plans')
                ? Http::response(['id' => 'plan_rzp_tls'], 200)
                : Http::response(['id' => 'sub_rzp_tls', 'status' => 'created'], 200);
        });

        $this->postJson(route('ajax.subscription.checkout'), [
            'subscription_plan_id' => $starter->id,
        ])->assertOk()->assertJsonPath('subscription_id', 'sub_rzp_tls');

        $this->assertNotEmpty($capturedOptions);
        foreach ($capturedOptions as $options) {
            $this->assertSame(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT, $options['crypto_method'] ?? null);
            $this->assertArrayNotHasKey(CURLOPT_SSLVERSION, $options['curl'] ?? []);
        }
    }

    public function test_webhook_verification_handles_valid_duplicate_and_invalid_signatures(): void
    {
        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        $org = Organization::query()->create(['name' => 'Webhook Org', 'currency' => 'INR']);
        $sub = Subscription::query()->create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'pending_payment',
            'razorpay_subscription_id' => 'sub_wh_001',
            'base_amount_paise' => 249900,
            'discount_paise' => 0,
            'initial_charge_paise' => 249900,
            'currency' => 'INR',
        ]);

        $payload = json_encode([
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'id' => 'sub_wh_001',
                        'current_start' => now()->timestamp,
                        'current_end' => now()->addMonth()->timestamp,
                    ],
                ],
                'payment' => [
                    'entity' => [
                        'id' => 'pay_wh_001',
                        'subscription_id' => 'sub_wh_001',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // Invalid signature is rejected and logged.
        $this->postJson(route('api.webhooks.razorpay'), json_decode($payload, true), [
            'X-Razorpay-Signature' => 'invalid_sig',
            'X-Razorpay-Event-Id' => 'evt_001',
        ])->assertStatus(401);

        $this->assertDatabaseHas('payment_gateway_logs', [
            'direction' => 'inbound_webhook',
            'signature_valid' => false,
            'status' => 'failed',
        ]);

        // Valid signature activates subscription.
        $validSig = hash_hmac('sha256', $payload, 'whsec_test_789');
        $this->call('POST', route('api.webhooks.razorpay'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $validSig,
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_001',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame('active', $sub->fresh()->status);

        // Duplicate event ID is ignored idempotently.
        $this->call('POST', route('api.webhooks.razorpay'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $validSig,
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_001',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk()->assertJsonPath('status', 'duplicate_ignored');
    }

    public function test_subscription_menu_is_visible_to_all_roles_and_non_admins_cannot_manage_billing(): void
    {
        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        $org = Organization::query()->create(['name' => 'Roles Org', 'currency' => 'INR']);
        Subscription::query()->create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'active',
            'base_amount_paise' => 249900,
            'discount_paise' => 0,
            'initial_charge_paise' => 249900,
            'currency' => 'INR',
        ]);

        foreach (['org_admin', 'sales_manager', 'sales_rep', 'read_only'] as $role) {
            $user = User::factory()->create();
            $org->users()->attach($user->id, ['role' => $role]);

            if ($role === 'org_admin') {
                $this->actingAs($user)
                    ->withSession(['active_organization_id' => $org->id])
                    ->get(route('subscription.index'))
                    ->assertOk()
                    ->assertSee(route('subscription.index'), false)
                    ->assertSee('Starter Plan');
            } else {
                $this->actingAs($user)
                    ->withSession(['active_organization_id' => $org->id])
                    ->get(route('subscription.index'))
                    ->assertStatus(403);

                $this->actingAs($user)
                    ->withSession(['active_organization_id' => $org->id])
                    ->postJson(route('ajax.subscription.checkout'), [
                        'subscription_plan_id' => $starter->id,
                    ])
                    ->assertStatus(403);
            }
        }
    }
}
