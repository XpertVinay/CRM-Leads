<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WhatsAppAccount extends Model
{
    use BelongsToOrganization;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'organization_id',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'access_token_encrypted',
        'webhook_verify_token',
        'is_coexistence_enabled',
        'is_active',
        'status',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'webhook_verify_token',
    ];

    protected function casts(): array
    {
        return [
            'is_coexistence_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_account_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }

    public function setPlainAccessToken(string $token): void
    {
        $this->access_token_encrypted = Crypt::encryptString($token);
    }

    public function getPlainAccessToken(): string
    {
        $value = (string) $this->access_token_encrypted;
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
