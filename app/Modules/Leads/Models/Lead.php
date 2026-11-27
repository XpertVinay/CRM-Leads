<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Models\OrganizationSetting;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Modules\Tracking\Models\Touchpoint;
use App\Modules\Tracking\Models\TrackingLink;
use App\Support\Casts\EncryptedArray;
use App\Support\Casts\EncryptedString;
use App\Support\Security\HasBlindIndexes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Lead extends Model
{
    use BelongsToOrganization, HasBlindIndexes, HasCustomFields, HasFactory, SoftDeletes;

    protected $hidden = [
        'email_hash',
        'phone_hash',
    ];

    public const TYPES = ['B2B', 'B2C'];

    public const PIPELINE_STATUSES = [
        'new',
        'contacted',
        'interested',
        'qualified',
        'proposal_sent',
        'negotiation',
        'won',
        'lost',
        'on_hold',
    ];

    public const STATUSES = [
        'new',
        'contacted',
        'interested',
        'qualified',
        'proposal_sent',
        'negotiation',
        'won',
        'lost',
        'on_hold',
        'unqualified',
        'converted',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected string $customFieldEntity = 'lead';

    protected $fillable = [
        'organization_id',
        'lead_reference',
        'campaign_id',
        'tracking_link_id',
        'type',
        'status',
        'priority',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'employee_count',
        'location',
        'requirement',
        'source',
        'duplicate_count',
        'last_inquiry_at',
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
            'first_name' => EncryptedString::class,
            'last_name' => EncryptedString::class,
            'email' => EncryptedString::class,
            'phone' => EncryptedString::class,
            'company_name' => EncryptedString::class,
            'location' => EncryptedString::class,
            'requirement' => EncryptedString::class,
            'employee_count' => 'integer',
            'duplicate_count' => 'integer',
            'last_inquiry_at' => 'datetime',
            'converted_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            if (empty($lead->lead_reference) && $lead->organization_id) {
                $prefix = OrganizationSetting::forOrganization((int) $lead->organization_id)->lead_reference_prefix ?: 'LD';
                do {
                    $candidate = strtoupper($prefix).'-'.now('UTC')->format('Ymd').'-'.strtoupper(Str::random(5));
                } while (static::withoutGlobalScopes()->withTrashed()
                    ->where('organization_id', $lead->organization_id)
                    ->where('lead_reference', $candidate)
                    ->exists());

                $lead->lead_reference = $candidate;
            }

            if (empty($lead->priority)) {
                $lead->priority = 'medium';
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class, 'tracking_link_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'lead_id')->orderByDesc('assigned_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class, 'lead_id')->orderByDesc('changed_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'lead_id')->orderBy('due_at');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activitable')->orderByDesc('created_at');
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
