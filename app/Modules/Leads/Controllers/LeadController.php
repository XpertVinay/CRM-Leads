<?php

namespace App\Modules\Leads\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\DTOs\LeadIngestionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadStatusHistory;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Leads\Services\LeadIngestionService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Activity;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', Lead::class);

        $tenant = $tenantContext->getTenant();
        $members = $tenant->users()
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $customFields = CustomFieldDefinition::query()
            ->where('entity_type', 'lead')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        return view('leads.index', [
            'types' => Lead::TYPES,
            'statuses' => Lead::STATUSES,
            'members' => $members,
            'customFields' => $customFields,
        ]);
    }

    public function show(Lead $lead): View
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'assignedUser',
            'campaign',
            'trackingLink',
            'assignments.assignedTo',
            'assignments.assignedBy',
            'statusHistories.changedBy',
            'followUps.assignedUser',
            'convertedContact',
            'convertedAccount',
            'convertedDeal.pipelineStage',
            'touchpoints.trackingLink',
        ]);

        $pipelines = Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('id')->get();
        $accounts = Account::query()
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('leads.show', [
            'lead' => $lead,
            'pipelines' => $pipelines,
            'accounts' => $accounts,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $query = $this->buildFilteredQuery($request);
        if ($query instanceof JsonResponse) {
            return $query;
        }

        $searchTerm = is_string($request->query('search')) ? Str::lower(trim((string) $request->query('search'))) : '';
        $perPage = $request->query('per_page');

        if ($searchTerm !== '') {
            $filtered = $query->get()->filter(fn (Lead $lead): bool => $this->matchesLeadSearch($lead, $searchTerm))->values();

            if (is_string($perPage) && ctype_digit($perPage) && (int) $perPage > 0) {
                $perPageInt = min((int) $perPage, 100);
                $page = LengthAwarePaginator::resolveCurrentPage();
                $paginator = new LengthAwarePaginator(
                    $filtered->forPage($page, $perPageInt)->values(),
                    $filtered->count(),
                    $perPageInt,
                    $page,
                );

                return response()->json([
                    'data' => $paginator->items(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ]);
            }

            return response()->json([
                'data' => $filtered,
            ]);
        }

        if (is_string($perPage) && ctype_digit($perPage) && (int) $perPage > 0) {
            $paginator = $query->paginate(min((int) $perPage, 100));

            return response()->json([
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    private function buildFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder|JsonResponse
    {
        $query = Lead::query()
            ->with(['assignedUser:id,name,email', 'campaign:id,name']);

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            if (!in_array($type, Lead::TYPES, true)) {
                return response()->json(['message' => 'Invalid lead type filter.'], 422);
            }
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            if (!in_array($status, Lead::STATUSES, true)) {
                return response()->json(['message' => 'Invalid lead status filter.'], 422);
            }
            $query->where('status', $status);
        }

        $priority = $request->query('priority');
        if (is_string($priority) && $priority !== '') {
            if (!in_array($priority, Lead::PRIORITIES, true)) {
                return response()->json(['message' => 'Invalid priority filter.'], 422);
            }
            $query->where('priority', $priority);
        }

        $campaignId = $request->query('campaign_id');
        if (is_string($campaignId) && $campaignId !== '' && ctype_digit($campaignId)) {
            $query->where('campaign_id', (int) $campaignId);
        }

        $source = $request->query('source');
        if (is_string($source) && trim($source) !== '') {
            $query->where('source', trim($source));
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_user_id');
        }

        $assignedUserId = $request->query('assigned_user_id');
        if (is_string($assignedUserId) && $assignedUserId !== '' && ctype_digit($assignedUserId)) {
            $query->where('assigned_user_id', (int) $assignedUserId);
        }

        $dateFrom = $request->query('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->query('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $allowedSorts = ['id', 'created_at', 'first_name', 'status', 'priority', 'source'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? (string) $request->query('sort_by') : 'id';
        $sortDir = strtolower((string) $request->query('sort_dir')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query;
    }

    public function showJson(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        return response()->json([
            'data' => $lead->load(['assignedUser:id,name,email', 'touchpoints']),
        ]);
    }

    public function store(
        Request $request,
        LeadIngestionService $ingestion,
        TenantContext $tenantContext,
    ): JsonResponse {
        Gate::authorize('create', Lead::class);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(Lead::TYPES)],
            'status' => ['sometimes', 'string', Rule::in(['new', 'contacted', 'qualified', 'unqualified'])],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => [
                Rule::requiredIf(static fn(): bool => $request->input('type') === 'B2B'),
                'nullable',
                'string',
                'max:191',
            ],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'location' => ['nullable', 'string', 'max:191'],
            'source' => ['nullable', 'string', 'max:100'],
            'assigned_user_id' => ['nullable', 'integer'],
            'custom_fields' => ['nullable', 'array'],
            'click_token' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['click_token']) && is_string($request->cookie('click_token'))) {
            $validated['click_token'] = $request->cookie('click_token');
        }

        $lead = $ingestion->ingest(
            $tenantContext->getTenant(),
            LeadIngestionData::fromArray($validated),
        );

        return response()->json([
            'message' => 'Lead created.',
            'data' => $lead,
        ], 201);
    }

    public function update(
        Request $request,
        Lead $lead,
        CustomFieldValidationService $customFieldValidator,
    ): JsonResponse {
        Gate::authorize('update', $lead);

        $targetType = (string) $request->input('type', $lead->type);

        $validated = $request->validate([
            'type' => ['sometimes', 'required', 'string', Rule::in(Lead::TYPES)],
            'status' => ['sometimes', 'required', 'string', Rule::in(array_diff(Lead::STATUSES, ['converted']))],
            'priority' => ['sometimes', 'required', 'string', Rule::in(Lead::PRIORITIES)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => [
                Rule::requiredIf(static fn(): bool => $targetType === 'B2B' && $request->exists('company_name')),
                'nullable',
                'string',
                'max:191',
            ],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'location' => ['nullable', 'string', 'max:191'],
            'requirement' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:100'],
            'status_reason' => ['nullable', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($request->exists('custom_fields')) {
            $mergedCustomFields = array_merge(
                is_array($lead->custom_fields) ? $lead->custom_fields : [],
                is_array($validated['custom_fields'] ?? null) ? $validated['custom_fields'] : [],
            );

            $validated['custom_fields'] = $customFieldValidator->validate(
                'lead',
                $mergedCustomFields,
                (int) $lead->organization_id,
            );
        }

        $oldStatus = $lead->status;
        $statusReason = $validated['status_reason'] ?? null;
        unset($validated['status_reason']);

        DB::transaction(function () use ($lead, $validated, $oldStatus, $statusReason, $request): void {
            $lead->update($validated);

            if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                LeadStatusHistory::create([
                    'organization_id' => (int) $lead->organization_id,
                    'lead_id' => (int) $lead->id,
                    'from_status' => $oldStatus,
                    'to_status' => $validated['status'],
                    'changed_by_user_id' => $request->user()?->id,
                    'reason' => $statusReason,
                    'changed_at' => now('UTC'),
                ]);

                Activity::create([
                    'organization_id' => (int) $lead->organization_id,
                    'user_id' => $request->user()?->id,
                    'activitable_type' => Lead::class,
                    'activitable_id' => (int) $lead->id,
                    'action' => 'status_change',
                    'description' => sprintf('Status changed from %s to %s', $oldStatus, $validated['status']),
                    'metadata' => [
                        'from_status' => $oldStatus,
                        'to_status' => $validated['status'],
                        'reason' => $statusReason,
                    ],
                ]);
            }
        });

        return response()->json([
            'message' => 'Lead updated.',
            'data' => $lead->fresh(),
        ]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return response()->json([
            'message' => 'Lead deleted.',
        ]);
    }

    public function assign(
        Request $request,
        Lead $lead,
        LeadAssignmentService $assignmentService,
    ): JsonResponse {
        Gate::authorize('assign', $lead);

        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $updated = $assignmentService->assign(
            $lead,
            isset($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : null,
        );

        return response()->json([
            'message' => 'Lead assignment updated.',
            'data' => $updated,
        ]);
    }

    public function convert(
        Request $request,
        Lead $lead,
        LeadConversionService $conversionService,
    ): JsonResponse {
        Gate::authorize('convert', $lead);

        $validated = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'account_name' => ['nullable', 'string', 'max:191'],
            'account_domain' => ['nullable', 'string', 'max:191'],
            'account_industry' => ['nullable', 'string', 'max:100'],
            'create_deal' => ['sometimes', 'boolean'],
            'deal_title' => ['nullable', 'string', 'max:191'],
            'deal_amount' => ['nullable', 'numeric', 'min:0'],
            'deal_currency' => ['nullable', 'string', 'size:3'],
            'pipeline_id' => ['nullable', 'integer'],
            'pipeline_stage_id' => ['nullable', 'integer'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $result = $conversionService->convert(
            $lead,
            LeadConversionData::fromArray($validated),
        );

        return response()->json([
            'message' => 'Lead converted successfully.',
            'data' => $result,
        ]);
    }

    public function bulkAction(Request $request, LeadAssignmentService $assignmentService): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $validated = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1', 'max:200'],
            'lead_ids.*' => ['integer'],
            'action' => ['required', 'string', Rule::in(['assign', 'status', 'priority'])],
            'assigned_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(array_diff(Lead::STATUSES, ['converted']))],
            'priority' => ['nullable', 'string', Rule::in(Lead::PRIORITIES)],
        ]);

        $leads = Lead::query()->whereIn('id', $validated['lead_ids'])->get();
        $updatedCount = 0;

        foreach ($leads as $lead) {
            if ($validated['action'] === 'assign') {
                Gate::authorize('assign', $lead);
                $assignmentService->assign($lead, isset($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : null);
                $updatedCount++;
            } elseif ($validated['action'] === 'status' && !empty($validated['status'])) {
                Gate::authorize('update', $lead);
                $oldStatus = $lead->status;
                if ($oldStatus !== $validated['status']) {
                    DB::transaction(function () use ($lead, $oldStatus, $validated, $request): void {
                        $lead->update(['status' => $validated['status']]);
                        LeadStatusHistory::create([
                            'organization_id' => (int) $lead->organization_id,
                            'lead_id' => (int) $lead->id,
                            'from_status' => $oldStatus,
                            'to_status' => $validated['status'],
                            'changed_by_user_id' => $request->user()?->id,
                            'changed_at' => now('UTC'),
                        ]);
                    });
                }
                $updatedCount++;
            } elseif ($validated['action'] === 'priority' && !empty($validated['priority'])) {
                Gate::authorize('update', $lead);
                $lead->update(['priority' => $validated['priority']]);
                $updatedCount++;
            }
        }

        return response()->json([
            'message' => sprintf('%d lead(s) updated.', $updatedCount),
            'updated_count' => $updatedCount,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $query = $this->buildFilteredQuery($request);
        if ($query instanceof JsonResponse) {
            return $query;
        }

        $searchTerm = is_string($request->query('search')) ? Str::lower(trim((string) $request->query('search'))) : '';

        return response()->streamDownload(function () use ($query, $searchTerm): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference',
                'Name',
                'Phone',
                'Email',
                'Company',
                'Type',
                'Status',
                'Priority',
                'Source',
                'Assigned To',
                'Created At',
            ]);

            foreach ($query->lazy(500) as $lead) {
                if ($searchTerm !== '' && ! $this->matchesLeadSearch($lead, $searchTerm)) {
                    continue;
                }
                fputcsv($handle, \App\Support\Security\CsvSanitizer::sanitizeRow([
                    $lead->lead_reference ?? '',
                    $lead->fullName(),
                    $lead->phone ?? '',
                    $lead->email ?? '',
                    $lead->company_name ?? '',
                    $lead->type,
                    $lead->status,
                    $lead->priority ?? 'medium',
                    $lead->source,
                    $lead->assignedUser?->name ?? 'Unassigned',
                    $lead->created_at?->toIso8601String() ?? '',
                ]));
            }

            fclose($handle);
        }, 'leads-export-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function matchesLeadSearch(Lead $lead, string $searchTerm): bool
    {
        foreach ([
            $lead->lead_reference,
            $lead->first_name,
            $lead->last_name,
            $lead->email,
            $lead->phone,
            $lead->company_name,
            $lead->location,
        ] as $value) {
            if (is_string($value) && $value !== '' && Str::contains(Str::lower($value), $searchTerm)) {
                return true;
            }
        }

        return false;
    }
}
