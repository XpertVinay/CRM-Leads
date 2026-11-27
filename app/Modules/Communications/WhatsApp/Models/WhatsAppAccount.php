<?php

namespace App\Modules\Communications\WhatsApp\Models;

use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Support\Casts\EncryptedString;
use App\Support\Security\DataEncrypter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

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
            'display_phone_number' => EncryptedString::class,
            'verified_name' => EncryptedString::class,
            'webhook_verify_token' => EncryptedString::class,
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

    /**
     * Determine whether the given organization has an active, connected WhatsApp Business API account.
     */
    public static function isConfiguredForOrganization(int $organizationId): bool
    {
        if ($organizationId <= 0 || ! Schema::hasTable('whatsapp_accounts')) {
            return false;
        }

        return static::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('status', 'connected')
            ->exists();
    }

    public function setPlainAccessToken(string $token): void
    {
        $this->access_token_encrypted = DataEncrypter::instance()->encryptString($token);
    }

    public function getPlainAccessToken(): string
    {
        $value = (string) $this->access_token_encrypted;
        if ($value === '') {
            return '';
        }

        $decrypted = DataEncrypter::instance()->decryptString($value);
        if ($decrypted !== $value) {
            return (string) $decrypted;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
