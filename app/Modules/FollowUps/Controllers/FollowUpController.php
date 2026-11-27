<?php

namespace App\Modules\FollowUps\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FollowUps\Models\FollowUp;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Tenancy\Context\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', FollowUp::class);

        $tenant = $tenantContext->getTenant();
        $members = $tenant->users()
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $leads = Lead::query()->orderByDesc('id')->limit(200)->get(['id', 'lead_reference', 'first_name', 'last_name', 'phone']);

        return view('follow-ups.index', [
            'types' => FollowUp::TYPES,
            'statuses' => FollowUp::STATUSES,
            'members' => $members,
            'leads' => $leads,
        ]);
    }

    public function list(Request $request, TenantContext $tenantContext): JsonResponse
    {
        Gate::authorize('viewAny', FollowUp::class);

        $tenant = $tenantContext->getTenant();
        $timezone = $tenant?->timezone ?? 'UTC';

        $query = FollowUp::query()
            ->with([
                'lead:id,lead_reference,first_name,last_name,phone,company_name,status',
                'assignedUser:id,name,email',
            ])
            ->orderBy('due_at');

        $bucket = $request->query('bucket');
        if ($bucket === 'overdue') {
            $query->overdue();
        } elseif ($bucket === 'today') {
            $query->today($timezone);
        } elseif ($bucket === 'upcoming') {
            $query->upcoming($timezone);
        }

        $status = $request->query('status');
        if (is_string($status) && in_array($status, FollowUp::STATUSES, true)) {
            $query->where('status', $status);
        }

        $assignedUserId = $request->query('assigned_user_id');
        if (is_string($assignedUserId) && ctype_digit($assignedUserId)) {
            $query->where('assigned_user_id', (int) $assignedUserId);
        }

        $leadId = $request->query('lead_id');
        if (is_string($leadId) && ctype_digit($leadId)) {
            $query->where('lead_id', (int) $leadId);
        }

        return response()->json([
            'data' => $query->get(),
            'summary' => [
                'overdue' => FollowUp::query()->overdue()->count(),
                'today' => FollowUp::query()->today($timezone)->count(),
                'upcoming' => FollowUp::query()->upcoming($timezone)->count(),
            ],
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        LeadAssignmentService $assignmentService,
    ): JsonResponse {
        Gate::authorize('create', FollowUp::class);

        $organization = $tenantContext->getTenant();
        $timezone = $organization?->timezone ?? 'UTC';

        $validated = $request->validate([
            'lead_id' => ['required', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', Rule::in(FollowUp::TYPES)],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = Lead::query()->findOrFail((int) $validated['lead_id']);
        $assignedUserId = isset($validated['assigned_user_id'])
            ? (int) $validated['assigned_user_id']
            : (int) ($lead->assigned_user_id ?: $request->user()->id);

        $assignmentService->assertOrganizationMember((int) $organization->id, $assignedUserId);

        $dueUtc = Carbon::parse($validated['due_at'], $timezone)->utc();

        $followUp = DB::transaction(function () use ($organization, $lead, $assignedUserId, $validated, $dueUtc, $request): FollowUp {
            $fu = FollowUp::create([
                'organization_id' => (int) $organization->id,
                'lead_id' => (int) $lead->id,
                'assigned_user_id' => $assignedUserId,
                'created_by_user_id' => $request->user()?->id,
                'type' => $validated['type'],
                'due_at' => $dueUtc,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            Activity::create([
                'organization_id' => (int) $organization->id,
                'user_id' => $request->user()?->id,
                'activitable_type' => Lead::class,
                'activitable_id' => (int) $lead->id,
                'action' => 'follow_up_scheduled',
                'description' => sprintf('Scheduled %s follow-up for %s', $fu->type, $dueUtc->toDateTimeString().' UTC'),
                'metadata' => ['follow_up_id' => $fu->id, 'type' => $fu->type, 'due_at' => $dueUtc->toIso8601String()],
            ]);

            return $fu;
        });

        return response()->json([
            'message' => 'Follow-up scheduled successfully.',
            'data' => $followUp->load(['lead:id,lead_reference,first_name,last_name', 'assignedUser:id,name']),
        ], 201);
    }

    public function update(Request $request, FollowUp $followUp, TenantContext $tenantContext): JsonResponse
    {
        Gate::authorize('update', $followUp);

        $organization = $tenantContext->getTenant();
        $timezone = $organization?->timezone ?? 'UTC';

        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(FollowUp::STATUSES)],
            'outcome' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'required', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $updated = DB::transaction(function () use ($followUp, $validated, $timezone, $organization, $request): FollowUp {
            $payload = [];
            if (array_key_exists('status', $validated)) {
                $payload['status'] = $validated['status'];
                if ($validated['status'] === 'completed' && $followUp->completed_at === null) {
                    $payload['completed_at'] = now('UTC');
                }
            }
            if (array_key_exists('outcome', $validated)) {
                $payload['outcome'] = $validated['outcome'];
            }
            if (array_key_exists('notes', $validated)) {
                $payload['notes'] = $validated['notes'];
            }
            if (array_key_exists('due_at', $validated)) {
                $payload['due_at'] = Carbon::parse($validated['due_at'], $timezone)->utc();
            }
            if (array_key_exists('next_follow_up_at', $validated) && $validated['next_follow_up_at'] !== null) {
                $payload['next_follow_up_at'] = Carbon::parse($validated['next_follow_up_at'], $timezone)->utc();
            }

            $followUp->update($payload);

            if (($payload['status'] ?? null) === 'completed') {
                Activity::create([
                    'organization_id' => (int) $organization->id,
                    'user_id' => $request->user()?->id,
                    'activitable_type' => Lead::class,
                    'activitable_id' => (int) $followUp->lead_id,
                    'action' => 'follow_up_completed',
                    'description' => 'Follow-up completed'.($followUp->outcome ? ': '.$followUp->outcome : ''),
                    'metadata' => ['follow_up_id' => $followUp->id, 'outcome' => $followUp->outcome],
                ]);

                if (! empty($payload['next_follow_up_at'])) {
                    FollowUp::create([
                        'organization_id' => (int) $organization->id,
                        'lead_id' => (int) $followUp->lead_id,
                        'assigned_user_id' => $followUp->assigned_user_id,
                        'created_by_user_id' => $request->user()?->id,
                        'type' => $followUp->type,
                        'due_at' => $payload['next_follow_up_at'],
                        'status' => 'pending',
                        'notes' => 'Auto-scheduled from previous follow-up',
                    ]);
                }
            }

            return $followUp->fresh(['lead:id,lead_reference,first_name,last_name', 'assignedUser:id,name']);
        });

        return response()->json([
            'message' => 'Follow-up updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(FollowUp $followUp): JsonResponse
    {
        Gate::authorize('delete', $followUp);

        $followUp->delete();

        return response()->json(['message' => 'Follow-up deleted successfully.']);
    }
}
