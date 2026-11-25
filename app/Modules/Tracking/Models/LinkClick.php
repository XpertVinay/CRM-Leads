<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkClick extends Model
{
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'tracking_link_id',
        'click_token',
        'ip_hash',
        'ip_address',
        'visitor_hash',
        'user_agent',
        'device',
        'browser',
        'referer',
        'landing_page',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class, 'tracking_link_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(Touchpoint::class, 'link_click_id');
    }
}
