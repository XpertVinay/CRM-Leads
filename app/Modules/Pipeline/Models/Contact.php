<?php

namespace App\Modules\Pipeline\Models;

use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Modules\Tracking\Models\Touchpoint;
use App\Support\Casts\EncryptedArray;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToOrganization, HasBlindIndexes, HasCustomFields, HasFactory, SoftDeletes;

    protected $hidden = [
        'email_hash',
        'phone_hash',
    ];

    public const PREFERRED_CHANNELS = ['whatsapp', 'email', 'phone'];

    protected string $customFieldEntity = 'contact';

    protected $fillable = [
        'organization_id',
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'preferred_channel',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'first_name' => EncryptedString::class,
            'last_name' => EncryptedString::class,
            'email' => EncryptedString::class,
            'phone' => EncryptedString::class,
            'job_title' => EncryptedString::class,
            'custom_fields' => EncryptedArray::class,
        ];
    }

    protected function blindIndexMap(): array
    {
        return [
            'email' => ['column' => 'email_hash', 'type' => 'email'],
            'phone' => ['column' => 'phone_hash', 'type' => 'phone'],
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'contact_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(Touchpoint::class, 'contact_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
