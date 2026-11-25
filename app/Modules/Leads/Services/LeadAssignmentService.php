<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
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

    public function assign(Lead $lead, ?int $assignedUserId): Lead
    {
        if ($assignedUserId !== null) {
            $this->assertOrganizationMember((int) $lead->organization_id, $assignedUserId);
        }

        $lead->update([
            'assigned_user_id' => $assignedUserId,
        ]);

        return $lead->fresh();
    }
}
