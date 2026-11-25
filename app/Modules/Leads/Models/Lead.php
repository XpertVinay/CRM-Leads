<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToOrganization, HasCustomFields, HasFactory, SoftDeletes;

    public const TYPES = ['B2B', 'B2C'];

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];

    protected string $customFieldEntity = 'lead';

    protected $fillable = [
        'organization_id',
        'type',
        'status',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'employee_count',
        'location',
        'source',
        'assigned_user_id',
        'converted_contact_id',
        'converted_account_id',
        'converted_deal_id',
        'converted_at',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'employee_count' => 'integer',
            'converted_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    public function convertedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'converted_account_id');
    }

    public function convertedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'converted_deal_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(Touchpoint::class, 'lead_id');
    }

    public function isB2B(): bool
    {
        return $this->type === 'B2B';
    }

    public function isB2C(): bool
    {
        return $this->type === 'B2C';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted' || $this->converted_at !== null;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
