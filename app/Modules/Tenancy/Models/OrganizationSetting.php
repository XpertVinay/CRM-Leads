<?php

namespace App\Modules\Tenancy\Models;

use App\Models\Organization;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    use BelongsToOrganization;

    public const DUPLICATE_STRATEGIES = ['merge_history', 'create_new', 'reject'];

    protected $fillable = [
        'organization_id',
        'whatsapp_phone',
        'whatsapp_default_message',
        'duplicate_lead_strategy',
        'capture_ip_address',
        'lead_reference_prefix',
        'require_email',
        'require_company',
    ];

    protected function casts(): array
    {
        return [
            'capture_ip_address' => 'boolean',
            'require_email' => 'boolean',
            'require_company' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public static function forOrganization(Organization|int $organization): self
    {
        $organizationId = $organization instanceof Organization ? (int) $organization->id : $organization;

        return static::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'duplicate_lead_strategy' => 'merge_history',
                'capture_ip_address' => false,
                'lead_reference_prefix' => 'LD',
                'require_email' => false,
                'require_company' => false,
                'whatsapp_default_message' => 'Hello, I am {name} (Ref: {lead_ref}). I would like to inquire about {campaign}.',
            ],
        );
    }
}
