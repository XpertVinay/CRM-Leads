<?php

namespace App\Models;

use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasBlindIndexes, HasFactory, SoftDeletes;

    protected $hidden = [
        'primary_contact_email_hash',
    ];

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'registration_identifier',
        'primary_contact_email',
        'status',
        'timezone',
        'currency',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'primary_contact_email' => EncryptedString::class,
            'settings' => 'array',
        ];
    }

    protected function blindIndexMap(): array
    {
        return [
            'primary_contact_email' => ['column' => 'primary_contact_email_hash', 'type' => 'email'],
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (empty($organization->uuid)) {
                $organization->uuid = (string) Str::uuid();
            }

            if (empty($organization->slug)) {
                $baseSlug = Str::slug($organization->name ?: 'organization');
                $baseSlug = $baseSlug !== '' ? $baseSlug : 'organization';
                $slug = $baseSlug;
                $suffix = 1;

                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $suffix++;
                    $slug = "{$baseSlug}-{$suffix}";
                }

                $organization->slug = $slug;
            }

            if (empty($organization->status)) {
                $organization->status = 'active';
            }

            if (empty($organization->timezone)) {
                $organization->timezone = 'UTC';
            }

            if (empty($organization->currency)) {
                $organization->currency = 'USD';
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot(['role', 'invited_by_user_id'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class, 'organization_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class, 'organization_id');
    }

    public function customFieldDefinitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class, 'organization_id');
    }

    public function setting(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class, 'organization_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'organization_id');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'organization_id')->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->trashed();
    }
}
