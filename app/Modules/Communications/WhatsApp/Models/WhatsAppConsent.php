<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConsent extends Model
{
    use BelongsToOrganization, HasBlindIndexes;

    protected $table = 'whatsapp_consents';

    protected $hidden = [
        'phone_hash',
    ];

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
            'phone' => EncryptedString::class,
            'ip_address' => EncryptedString::class,
            'is_opted_in' => 'boolean',
            'consent_timestamp' => 'datetime',
        ];
    }

    protected function blindIndexMap(): array
    {
        return [
            'phone' => ['column' => 'phone_hash', 'type' => 'phone'],
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
