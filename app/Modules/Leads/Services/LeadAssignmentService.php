<?php

namespace App\Modules\Leads\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditService;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadAssignment;
use App\Modules\Notifications\Notifications\LeadAssignedNotification;
use App\Modules\Pipeline\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function __construct(
        private readonly ?AuditService $auditService = null,
    ) {}

    public function assertOrganizationMember(int $organizationId, int $userId, string $errorField = 'assigned_user_id'): void
    {
        $isMember = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                $errorField => ['Assigned user must be an active member of this organization.'],
            ]);
        }
    }

    public function resolveAutoAssigneeId(int $organizationId, ?int $preferredUserId, bool $roundRobin): ?int
    {
        if ($preferredUserId !== null) {
            $this->assertOrganizationMember($organizationId, $preferredUserId);

            return $preferredUserId;
        }

        if (! $roundRobin) {
            return null;
        }

        $memberIds = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        if ($memberIds->isEmpty()) {
            return null;
        }

        $counts = LeadAssignment::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('assigned_to_user_id', $memberIds->all())
            ->selectRaw('assigned_to_user_id, COUNT(*) as assignment_count, MAX(assigned_at) as last_assigned_at')
            ->groupBy('assigned_to_user_id')
            ->get()
            ->keyBy('assigned_to_user_id');

        $chosenId = $memberIds->sortBy(function (int $userId) use ($counts): array {
            $row = $counts->get($userId);

            return [
                $row ? (int) $row->assignment_count : 0,
                $row && $row->last_assigned_at ? (string) $row->last_assigned_at : '',
                $userId,
            ];
        })->first();

        return $chosenId !== null ? (int) $chosenId : null;
    }

    public function assign(Lead $lead, ?int $assignedUserId): Lead
    {
        if ($assignedUserId !== null) {
            $this->assertOrganizationMember((int) $lead->organization_id, $assignedUserId);
        }

        $oldOwnerId = $lead->assigned_user_id;

        DB::transaction(function () use ($lead, $assignedUserId, $oldOwnerId): void {
            $now = now('UTC');

            if ($oldOwnerId !== null && $oldOwnerId !== $assignedUserId) {
                LeadAssignment::query()
                    ->where('lead_id', $lead->id)
                    ->whereNull('unassigned_at')
                    ->update(['unassigned_at' => $now]);
            }

            $lead->update([
                'assigned_user_id' => $assignedUserId,
            ]);

            if ($assignedUserId !== null && $assignedUserId !== $oldOwnerId) {
                LeadAssignment::create([
                    'organization_id' => (int) $lead->organization_id,
                    'lead_id' => (int) $lead->id,
                    'assigned_to_user_id' => $assignedUserId,
                    'assigned_by_user_id' => Auth::id(),
                    'assigned_at' => $now,
                ]);
            }

            if ($oldOwnerId !== $assignedUserId) {
                Activity::create([
                    'organization_id' => (int) $lead->organization_id,
                    'user_id' => Auth::id(),
                    'activitable_type' => Lead::class,
                    'activitable_id' => (int) $lead->id,
                    'action' => 'assignment',
                    'description' => $assignedUserId !== null
                        ? sprintf('Lead assigned to user #%d', $assignedUserId)
                        : 'Lead unassigned',
                    'metadata' => [
                        'from_user_id' => $oldOwnerId,
                        'to_user_id' => $assignedUserId,
                    ],
                ]);
            }
        });

        $fresh = $lead->fresh();

        if ($assignedUserId !== null && $assignedUserId !== $oldOwnerId) {
            $assignee = User::find($assignedUserId);
            if ($assignee) {
                $assignee->notify(new LeadAssignedNotification($fresh));
            }
        }

        ($this->auditService ?? app(AuditService::class))->record(
            'lead_assigned',
            $fresh,
            ['assigned_user_id' => $oldOwnerId],
            ['assigned_user_id' => $assignedUserId],
        );

        return $fresh;
    }
}
