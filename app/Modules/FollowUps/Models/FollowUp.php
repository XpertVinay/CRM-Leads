<?php

namespace App\Modules\FollowUps\Models;

use App\Models\User;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    use BelongsToOrganization, HasFactory;

    public const TYPES = ['call', 'whatsapp', 'email', 'meeting', 'demo'];

    public const STATUSES = ['pending', 'completed', 'cancelled'];

    protected $fillable = [
        'organization_id',
        'lead_id',
        'assigned_user_id',
        'created_by_user_id',
        'type',
        'due_at',
        'completed_at',
        'next_follow_up_at',
        'status',
        'notes',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('due_at', '<', now('UTC'));
    }

    public function scopeToday(Builder $query, string $timezone = 'UTC'): Builder
    {
        $start = now($timezone)->startOfDay()->utc();
        $end = now($timezone)->endOfDay()->utc();

        return $query->where('status', 'pending')->whereBetween('due_at', [$start, $end]);
    }

    public function scopeUpcoming(Builder $query, string $timezone = 'UTC'): Builder
    {
        $endOfToday = now($timezone)->endOfDay()->utc();

        return $query->where('status', 'pending')->where('due_at', '>', $endOfToday);
    }
}
