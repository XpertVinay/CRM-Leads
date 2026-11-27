<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\Organization;
use App\Models\User;
use App\Support\Casts\EncryptedArray;
use App\Support\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'user_id',
        'direction',
        'event_or_endpoint',
        'http_method',
        'http_status',
        'razorpay_subscription_id',
        'razorpay_payment_id',
        'razorpay_order_id',
        'razorpay_event_id',
        'request_headers',
        'request_payload',
        'response_payload',
        'signature_valid',
        'status',
        'error_message',
        'duration_ms',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'request_headers' => EncryptedArray::class,
            'request_payload' => EncryptedArray::class,
            'response_payload' => EncryptedArray::class,
            'signature_valid' => 'boolean',
            'duration_ms' => 'integer',
            'ip_address' => EncryptedString::class,
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
