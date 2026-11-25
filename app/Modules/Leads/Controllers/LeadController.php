<?php

namespace App\Modules\Leads\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Leads\DTOs\LeadConversionData;
use App\Modules\Leads\DTOs\LeadIngestionData;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadAssignmentService;
use App\Modules\Leads\Services\LeadConversionService;
use App\Modules\Leads\Services\LeadIngestionService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', Lead::class);

        $tenant = $tenantContext->getTenant();
        $members = $tenant->users()->orderBy('users.name')->get();
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
            'convertedContact',
            'convertedAccount',
            'convertedDeal.pipelineStage',
            'touchpoints.trackingLink',
        ]);

        $pipelines = Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('id')->get();
        $accounts = Account::query()->orderBy('name')->get();

        return view('leads.show', [
            'lead' => $lead,
            'pipelines' => $pipelines,
            'accounts' => $accounts,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query()
            ->with('assignedUser:id,name,email')
            ->orderByDesc('id');

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            if (! in_array($type, Lead::TYPES, true)) {
                return response()->json(['message' => 'Invalid lead type filter.'], 422);
            }
            $query->where('type', $type);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            if (! in_array($status, Lead::STATUSES, true)) {
                return response()->json(['message' => 'Invalid lead status filter.'], 422);
            }
            $query->where('status', $status);
        }

        $assignedUserId = $request->query('assigned_user_id');
        if (is_string($assignedUserId) && $assignedUserId !== '' && ctype_digit($assignedUserId)) {
            $query->where('assigned_user_id', (int) $assignedUserId);
        }

        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $needle = '%'.Str::lower(trim($search)).'%';
            $query->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$needle]);
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
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
                Rule::requiredIf(static fn (): bool => $request->input('type') === 'B2B'),
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
            'status' => ['sometimes', 'required', 'string', Rule::in(['new', 'contacted', 'qualified', 'unqualified'])],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => [
                Rule::requiredIf(static fn (): bool => $targetType === 'B2B' && $request->exists('company_name')),
                'nullable',
                'string',
                'max:191',
            ],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'location' => ['nullable', 'string', 'max:191'],
            'source' => ['nullable', 'string', 'max:100'],
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

        $lead->update($validated);

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
}
