<?php

namespace App\Modules\Campaigns\Models;

use App\Models\User;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Modules\Tracking\Models\TrackingLink;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const TYPES = ['whatsapp', 'paid_ads', 'social', 'website', 'email', 'event', 'referral', 'other'];

    public const PLATFORMS = ['facebook', 'instagram', 'google_ads', 'linkedin', 'website', 'whatsapp', 'youtube', 'other'];

    public const STATUSES = ['draft', 'active', 'paused', 'completed'];

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'type',
        'platform',
        'budget',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trackingLinks(): HasMany
    {
        return $this->hasMany(TrackingLink::class, 'campaign_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'campaign_id');
    }
}
