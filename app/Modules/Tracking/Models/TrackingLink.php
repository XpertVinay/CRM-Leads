<?php

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldRegistry;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TrackingLink extends Model
{
    use BelongsToOrganization, HasFactory;

    public const CHANNELS = ['ad', 'whatsapp', 'email', 'social', 'qr', 'organic'];

    protected $fillable = [
        'organization_id',
        'campaign_id',
        'code',
        'slug',
        'name',
        'destination_url',
        'channel',
        'source',
        'medium',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ad_spend',
        'whatsapp_number',
        'whatsapp_message',
        'capture_custom_field_ids',
        'assigned_user_id',
        'auto_assign_round_robin',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'ad_spend' => 'decimal:2',
            'capture_custom_field_ids' => 'array',
            'auto_assign_round_robin' => 'boolean',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function selectedLeadCustomFields(): Collection
    {
        $selectedIds = array_values(array_unique(array_map(
            'intval',
            is_array($this->capture_custom_field_ids) ? $this->capture_custom_field_ids : []
        )));

        if ($selectedIds === []) {
            return collect();
        }

        return app(CustomFieldRegistry::class)
            ->forEntity('lead', (int) $this->organization_id)
            ->filter(static fn (CustomFieldDefinition $def): bool => in_array((int) $def->id, $selectedIds, true))
            ->values();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'tracking_link_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class, 'tracking_link_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(Touchpoint::class, 'tracking_link_id');
    }

    public function shortUrl(): string
    {
        return url('/l/'.$this->code);
    }

    public function captureUrl(): string
    {
        return url('/go/'.($this->slug ?: $this->code));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }
}
