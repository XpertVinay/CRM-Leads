<?php

namespace App\Modules\Tenancy\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RbacUserPermission extends Model
{
    protected $table = 'rbac_user_permissions';

    protected $fillable = [
        'organization_id',
        'user_id',
        'permission_id',
        'allowed',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(RbacPermission::class, 'permission_id');
    }
}
