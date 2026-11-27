<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends Model
{
    use BelongsToOrganization, HasBlindIndexes;

    protected $table = 'organization_invitations';

    protected $hidden = [
        'token_hash',
        'email_hash',
    ];

    protected $fillable = [
        'organization_id',
        'invited_by_user_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'email' => EncryptedString::class,
            'token' => EncryptedString::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected function blindIndexMap(): array
    {
        return [
            'email' => ['column' => 'email_hash', 'type' => 'email'],
            'token' => ['column' => 'token_hash', 'type' => 'token'],
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
