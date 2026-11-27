<?php

namespace App\Modules\Compliance\Models;

use App\Models\User;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Support\Casts\EncryptedArray;
use App\Support\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'organization_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => EncryptedArray::class,
            'new_values' => EncryptedArray::class,
            'ip_address' => EncryptedString::class,
            'user_agent' => EncryptedString::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
