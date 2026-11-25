<?php

namespace App\Modules\Tenancy\Models;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use BelongsToOrganization;

    protected $table = 'api_tokens';

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'abilities',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>|null  $abilities
     * @return array{token: self, plain_text_token: string}
     */
    public static function issue(
        Organization $organization,
        User $user,
        string $name,
        ?array $abilities = null,
        ?DateTimeInterface $expiresAt = null,
    ): array {
        $plainText = 'crm_'.Str::random(40);
        $tokenHash = hash('sha256', $plainText);

        $apiToken = static::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => $tokenHash,
            'token_prefix' => substr($plainText, 0, 12),
            'abilities' => $abilities ?? ['*'],
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $apiToken,
            'plain_text_token' => $plainText,
        ];
    }
}
