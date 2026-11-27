<?php

namespace App\Modules\Pipeline\Models;

use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Support\Casts\EncryptedArray;
use App\Support\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToOrganization, HasCustomFields, HasFactory, SoftDeletes;

    protected string $customFieldEntity = 'account';

    protected $fillable = [
        'organization_id',
        'name',
        'domain',
        'industry',
        'annual_revenue',
        'employee_count',
        'billing_address',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'name' => EncryptedString::class,
            'domain' => EncryptedString::class,
            'annual_revenue' => 'decimal:2',
            'employee_count' => 'integer',
            'billing_address' => EncryptedArray::class,
            'custom_fields' => EncryptedArray::class,
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'account_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'account_id');
    }
}
