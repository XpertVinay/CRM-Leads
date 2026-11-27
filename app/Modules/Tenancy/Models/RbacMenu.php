<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class RbacMenu extends Model
{
    protected $table = 'rbac_menus';

    protected $fillable = [
        'label',
        'route_name',
        'route_pattern',
        'icon',
        'permission_slug',
        'section',
        'superadmin_only',
        'requires_org',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'superadmin_only' => 'boolean',
            'requires_org' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
