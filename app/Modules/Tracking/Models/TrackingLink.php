<?php

namespace App\Modules\Tracking\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingLink extends Model
{
    use BelongsToOrganization, HasFactory;

    public const CHANNELS = ['ad', 'whatsapp', 'email', 'social', 'qr', 'organic'];

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'destination_url',
        'channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ad_spend',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ad_spend' => 'decimal:2',
            'is_active' => 'boolean',
        ];
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
}
