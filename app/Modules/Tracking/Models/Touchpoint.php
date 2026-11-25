<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Touchpoint extends Model
{
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    public const MILESTONES = [
        'click',
        'lead_captured',
        'conversation_qualified',
        'deal_won',
    ];

    protected $fillable = [
        'organization_id',
        'tracking_link_id',
        'link_click_id',
        'lead_id',
        'contact_id',
        'deal_id',
        'event_milestone',
        'occurred_at',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class, 'tracking_link_id');
    }

    public function linkClick(): BelongsTo
    {
        return $this->belongsTo(LinkClick::class, 'link_click_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}
