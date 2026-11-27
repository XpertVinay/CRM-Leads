<?php

namespace App\Modules\Subscriptions\Models;

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SubscriptionPlan extends Model
{
    public const CACHE_KEY_ACTIVE = 'subscription_plans.active';

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_paise',
        'currency',
        'billing_interval',
        'lead_limit',
        'user_limit',
        'extra_user_price_paise',
        'features',
        'razorpay_plan_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'lead_limit' => 'integer',
            'user_limit' => 'integer',
            'extra_user_price_paise' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            static::flushCache();
        });

        static::deleted(function (): void {
            static::flushCache();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }

    /**
     * @return Collection<int, self>
     */
    public static function activeCached(): Collection
    {
        if (! Schema::hasTable('subscription_plans')) {
            return new Collection();
        }

        $cached = Cache::get(self::CACHE_KEY_ACTIVE);
        if ($cached instanceof Collection && $cached->isNotEmpty()) {
            return $cached;
        }

        $plans = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($plans->isEmpty() && ! app()->runningUnitTests()) {
            (new SubscriptionPlanSeeder())->run();
            $plans = static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($plans->isNotEmpty()) {
            Cache::put(self::CACHE_KEY_ACTIVE, $plans, 3600);
        }

        return $plans;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    public function priceInRupees(): float
    {
        return $this->price_paise / 100;
    }

    public function formattedPrice(): string
    {
        $symbol = $this->currency === 'INR' ? '₹' : $this->currency . ' ';

        return $symbol . number_format((int) round($this->price_paise / 100));
    }

    public function formattedExtraUserPrice(): string
    {
        $symbol = $this->currency === 'INR' ? '₹' : $this->currency . ' ';

        return $symbol . number_format((int) round($this->extra_user_price_paise / 100));
    }
}
