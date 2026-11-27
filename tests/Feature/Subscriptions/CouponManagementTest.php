<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\CouponRedemption;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubscriptionPlanSeeder::class);
    }

    private function createActiveOrgFor(User $user, string $role = 'org_admin'): Organization
    {
        $org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org-' . uniqid(),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'status' => 'active',
        ]);

        $org->users()->attach($user->id, ['role' => $role]);

        $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        Subscription::query()->create([
            'organization_id' => $org->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'active',
            'base_amount_paise' => $starter->price_paise,
            'discount_paise' => 0,
            'initial_charge_paise' => $starter->price_paise,
            'currency' => 'INR',
        ]);

        return $org;
    }

    public function test_non_superadmin_cannot_access_coupons_page_or_crud_endpoints(): void
    {
        $admin = User::factory()->create(['is_superadmin' => false]);
        $org = $this->createActiveOrgFor($admin, 'org_admin');

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->get(route('coupons.index'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->getJson(route('ajax.coupons.index'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson(route('ajax.coupons.store'), [
                'code' => 'HACK50',
                'discount_type' => 'percentage',
                'discount_value' => 50,
            ])
            ->assertStatus(403);
    }

    public function test_superadmin_can_crud_coupons_and_enforce_business_rules(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $org = $this->createActiveOrgFor($superadmin, 'org_admin');

        // 1. Page renders
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id, 'locale_selected' => true])
            ->get(route('coupons.index'))
            ->assertOk()
            ->assertSee('Coupons')
            ->assertSee('New Coupon');

        // 2. Percentage > 100 is rejected
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson(route('ajax.coupons.store'), [
                'code' => 'OVER100',
                'discount_type' => 'percentage',
                'discount_value' => 150,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_value']);

        // 3. Create valid percentage coupon with applicable plans
        $createRes = $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->postJson(route('ajax.coupons.store'), [
                'code' => '  save30 ',
                'description' => '30% off Gold plan',
                'discount_type' => 'percentage',
                'discount_value' => 30,
                'max_discount_paise' => 150000,
                'max_redemptions' => 50,
                'applicable_plan_codes' => ['gold'],
                'is_active' => true,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('coupon.code', 'SAVE30')
            ->assertJsonPath('coupon.applicable_plan_codes.0', 'gold');

        $couponId = $createRes->json('coupon.id');

        // 4. List & filter
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->getJson(route('ajax.coupons.index', ['q' => 'SAVE30', 'status' => 'active']))
            ->assertOk()
            ->assertJsonFragment(['code' => 'SAVE30']);

        // 5. Update & deactivate
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->patchJson(route('ajax.coupons.update', $couponId), [
                'code' => 'SAVE30',
                'description' => 'Updated description',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'applicable_plan_codes' => ['starter', 'gold'],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('coupon.discount_value', 25)
            ->assertJsonPath('coupon.is_active', false);

        // 6. Delete unredeemed coupon
        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->deleteJson(route('ajax.coupons.destroy', $couponId))
            ->assertOk();

        $this->assertDatabaseMissing('coupons', ['id' => $couponId]);
    }

    public function test_redeemed_coupon_cannot_be_deleted(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $org = $this->createActiveOrgFor($superadmin, 'org_admin');
        $subscription = Subscription::query()->where('organization_id', $org->id)->firstOrFail();

        $coupon = Coupon::query()->where('code', 'WELCOME20')->firstOrFail();
        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'organization_id' => $org->id,
            'subscription_id' => $subscription->id,
            'user_id' => $superadmin->id,
            'discount_paise' => 49980,
            'redeemed_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->withSession(['active_organization_id' => $org->id])
            ->deleteJson(route('ajax.coupons.destroy', $coupon->id))
            ->assertStatus(422);

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }
}
