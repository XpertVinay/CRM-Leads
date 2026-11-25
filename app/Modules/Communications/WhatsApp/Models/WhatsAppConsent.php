<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConsent extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_consents';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'phone',
        'is_opted_in',
        'consent_source',
        'consent_timestamp',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_opted_in' => 'boolean',
            'consent_timestamp' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
