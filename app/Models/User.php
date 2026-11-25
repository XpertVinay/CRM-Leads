<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Tenancy\Models\ApiToken;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot('role')
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

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        if (! $organizationId) {
            return null;
        }

        $role = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $this->id)
            ->value('role');

        return $role !== null ? (string) $role : null;
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

    public function isSuperadmin(): bool
    {
        return (bool) $this->is_superadmin;
    }
}
