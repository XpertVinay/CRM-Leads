<?php

namespace App\Modules\Leads\Services;

use App\Models\Organization;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Leads\DTOs\LeadIngestionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use App\Modules\Tracking\Events\TouchpointCreatedEvent;
use App\Modules\Tracking\Models\LinkClick;
use App\Modules\Tracking\Models\Touchpoint;
use Illuminate\Support\Facades\DB;

class LeadIngestionService
{
    public function __construct(
        private readonly CustomFieldValidationService $customFieldValidator,
        private readonly LeadAssignmentService $assignmentService,
    ) {}

    public function ingest(Organization|int $organization, LeadIngestionData $data): Lead
    {
        $organizationId = $organization instanceof Organization ? (int) $organization->id : $organization;

        if ($data->assignedUserId !== null) {
            $this->assignmentService->assertOrganizationMember($organizationId, $data->assignedUserId);
        }

        $validatedCustomFields = $this->customFieldValidator->validate(
            'lead',
            $data->customFields,
            $organizationId,
        );

        /** @var array{lead: Lead, touchpoint: ?Touchpoint} $result */
        $result = DB::transaction(function () use ($organizationId, $data, $validatedCustomFields): array {
            $lead = Lead::create([
                'organization_id' => $organizationId,
                'type' => $data->type,
                'status' => $data->status,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'email' => $data->email,
                'phone' => $data->phone,
                'company_name' => $data->type === 'B2B' ? $data->companyName : null,
                'job_title' => $data->jobTitle,
                'employee_count' => $data->type === 'B2B' ? $data->employeeCount : null,
                'location' => $data->type === 'B2C' ? $data->location : $data->location,
                'source' => $data->source,
                'assigned_user_id' => $data->assignedUserId,
                'custom_fields' => $validatedCustomFields !== [] ? $validatedCustomFields : null,
            ]);

            $touchpoint = null;
            if ($data->clickToken !== null) {
                $click = LinkClick::withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organizationId)
                    ->where('click_token', $data->clickToken)
                    ->first();

                if ($click !== null) {
                    $now = now();
                    $touchpoint = Touchpoint::create([
                        'organization_id' => $organizationId,
                        'tracking_link_id' => (int) $click->tracking_link_id,
                        'link_click_id' => (int) $click->id,
                        'lead_id' => (int) $lead->id,
                        'event_milestone' => 'lead_captured',
                        'occurred_at' => $now,
                        'metadata' => ['source' => $lead->source],
                        'created_at' => $now,
                    ]);
                }
            }

            return [
                'lead' => $lead,
                'touchpoint' => $touchpoint,
            ];
        });

        if ($result['touchpoint'] !== null) {
            TouchpointCreatedEvent::dispatch(
                $organizationId,
                (int) $result['touchpoint']->id,
                'lead_captured',
            );
        }

        return $result['lead'];
    }
}
