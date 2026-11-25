<?php

namespace App\Modules\Pipeline\Models;

use App\Models\User;
use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use BelongsToOrganization, HasCustomFields, HasFactory, SoftDeletes;

    protected string $customFieldEntity = 'deal';

    protected $fillable = [
        'organization_id',
        'pipeline_id',
        'pipeline_stage_id',
        'contact_id',
        'account_id',
        'assigned_user_id',
        'title',
        'amount',
        'currency',
        'expected_close_date',
        'stage_entered_at',
        'closed_at',
        'lost_reason',
        'is_stale',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'stage_entered_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_stale' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(Touchpoint::class, 'deal_id');
    }
}
