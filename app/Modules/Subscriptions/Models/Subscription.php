<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'subscription_plan_id',
        'coupon_id',
        'status',
        'razorpay_subscription_id',
        'razorpay_plan_id',
        'razorpay_customer_id',
        'base_amount_paise',
        'discount_paise',
        'initial_charge_paise',
        'is_mandate_only',
        'currency',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'base_amount_paise' => 'integer',
            'discount_paise' => 'integer',
            'initial_charge_paise' => 'integer',
            'is_mandate_only' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function gatewayLogs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class, 'subscription_id');
    }

    public function isOperational(): bool
    {
        return in_array($this->status, ['active', 'authenticated'], true);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }
}
