<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Tenancy\Models\ApiToken;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'phone', 'locale'])]
#[Hidden(['password', 'remember_token', 'is_superadmin', 'email_hash', 'phone_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasBlindIndexes, HasFactory, Notifiable;

    /**
     * Per-instance cache of organization_id => role lookups.
     *
     * @var array<int, ?string>
     */
    protected array $roleCache = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => EncryptedString::class,
            'email' => EncryptedString::class,
            'phone' => EncryptedString::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
        ];
    }

    protected function blindIndexMap(): array
    {
        return [
            'email' => ['column' => 'email_hash', 'type' => 'email'],
            'phone' => ['column' => 'phone_hash', 'type' => 'phone'],
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['role', 'invited_by_user_id'])
            ->withTimestamps();
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class, 'user_id');
    }

    public function roleIn(Organization|int|null $organization): ?string
    {
        if ($organization === null) {
            return null;
        }

        $organizationId = (int) ($organization instanceof Organization ? $organization->id : $organization);

        if ($organizationId <= 0) {
            return null;
        }

        if (array_key_exists($organizationId, $this->roleCache)) {
            return $this->roleCache[$organizationId];
        }

        $role = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $this->id)
            ->value('role');

        return $this->roleCache[$organizationId] = $role !== null ? (string) $role : null;
    }

    public function clearRoleCache(Organization|int|null $organization = null): void
    {
        if ($organization === null) {
            $this->roleCache = [];

            return;
        }

        $organizationId = (int) ($organization instanceof Organization ? $organization->id : $organization);
        unset($this->roleCache[$organizationId]);
    }

    /**
     * @param  array<int, string>|string  $roles
     */
    public function hasRoleIn(Organization|int|null $organization, array|string $roles): bool
    {
        $role = $this->roleIn($organization);

        if ($role === null) {
            return false;
        }

        $allowed = is_array($roles) ? $roles : [$roles];

        return in_array($role, $allowed, true);
    }

    public function hasPermissionIn(Organization|int|null $organization, string $permission): bool
    {
        return app(\App\Modules\Tenancy\Services\RbacService::class)->hasPermission($this, $organization, $permission);
    }

    public function isSuperadmin(): bool
    {
        return (bool) $this->is_superadmin;
    }
}
