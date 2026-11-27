<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_paise',
        'valid_from',
        'valid_until',
        'max_redemptions',
        'redemptions_count',
        'applicable_plan_codes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_discount_paise' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'applicable_plan_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = Str::upper(trim((string) $coupon->code));
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }
}
