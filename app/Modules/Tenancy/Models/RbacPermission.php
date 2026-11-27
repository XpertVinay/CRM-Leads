<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RbacPermission extends Model
{
    protected $table = 'rbac_permissions';

    protected $fillable = [
        'slug',
        'name',
        'module',
        'is_menu_item',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_menu_item' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RbacRole::class, 'rbac_role_permission', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
