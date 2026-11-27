<?php

namespace Database\Seeders;

use App\Modules\Subscriptions\Models\Coupon;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter Plan',
                'description' => 'Ideal for growing teams organizing ad inquiries and daily follow-ups.',
                'price_paise' => 249900,
                'currency' => 'INR',
                'billing_interval' => 'month',
                'lead_limit' => 25,
                'user_limit' => 2,
                'extra_user_price_paise' => 90000,
                'features' => [
                    'Full B2B & B2C pipeline & contact manager',
                    'Ad tracking links & embeddable inquiry forms',
                    'WhatsApp lead handoff, Email & Phone logging',
                    'Pre-limit alerts & auto-pause at 100% cap',
                    'Admin-approved pay-per-lead top-ups',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'gold',
                'name' => 'Gold Plan',
                'description' => 'For active sales teams needing higher lead volume, more seats, and lower overage rates.',
                'price_paise' => 499900,
                'currency' => 'INR',
                'billing_interval' => 'month',
                'lead_limit' => 75,
                'user_limit' => 5,
                'extra_user_price_paise' => 70000,
                'features' => [
                    'Everything in Starter with expanded lead & seat quotas',
                    '~25% lower overage rates across all industries',
                    'Shared WhatsApp Business templates & team reports',
                    'Priority pre-limit alerts & instant top-up controls',
                    'Priority onboarding & dedicated support',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }

        SubscriptionPlan::flushCache();

        Coupon::query()->updateOrCreate(
            ['code' => 'WELCOME20'],
            [
                'description' => '20% off your first billing cycle on Starter or Gold.',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_discount_paise' => null,
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addYear(),
                'max_redemptions' => null,
                'applicable_plan_codes' => ['starter', 'gold'],
                'is_active' => true,
            ],
        );
    }
}
