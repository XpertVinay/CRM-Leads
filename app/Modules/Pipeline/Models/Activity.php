<?php

namespace App\Modules\Pipeline\Models;

use App\Models\User;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use BelongsToOrganization;

    public const ACTIONS = [
        'note', 'call', 'email', 'meeting',
        'status_change', 'stage_change', 'assignment',
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'activitable_type',
        'activitable_id',
        'action',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function activitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
