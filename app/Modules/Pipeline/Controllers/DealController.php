<?php

namespace App\Modules\Pipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Pipeline\Models\Pipeline;
use App\Modules\Pipeline\Models\PipelineStage;
use App\Modules\Pipeline\Services\DealStageTransitionService;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DealController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Deal::class);

        $pipelines = Pipeline::query()->with('stages')->orderByDesc('is_default')->orderBy('id')->get();

        return view('deals.index', ['pipelines' => $pipelines]);
    }

    public function show(Deal $deal): View
    {
        Gate::authorize('view', $deal);

        $deal->load(['pipeline', 'pipelineStage', 'contact.account', 'account', 'assignedUser', 'touchpoints.trackingLink']);

        return view('deals.show', ['deal' => $deal]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Deal::class);

        $query = Deal::query()
            ->with(['pipelineStage:id,name,category', 'contact:id,first_name,last_name,email', 'assignedUser:id,name'])
            ->orderByDesc('id');

        $pipelineId = $request->query('pipeline_id');
        if (is_string($pipelineId) && ctype_digit($pipelineId)) {
            $query->where('pipeline_id', (int) $pipelineId);
        }

        $stageId = $request->query('pipeline_stage_id');
        if (is_string($stageId) && ctype_digit($stageId)) {
            $query->where('pipeline_stage_id', (int) $stageId);
        }

        $stale = $request->query('is_stale');
        if ($stale === '1' || $stale === 'true') {
            $query->where('is_stale', true);
        }

        $limit = min(max((int) $request->query('limit', 200), 1), 500);

        return response()->json(['data' => $query->limit($limit)->get()]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('create', Deal::class);

        $tenantId = (int) $tenantContext->getTenantId();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'pipeline_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('pipelines', $tenantId, false)],
            'pipeline_stage_id' => ['nullable', 'integer'],
            'contact_id' => ['required', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('contacts', $tenantId)],
            'account_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('accounts', $tenantId)],
            'assigned_user_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::organizationMemberExists($tenantId)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (empty($validated['pipeline_id'])) {
            $defaultPipeline = \App\Modules\Pipeline\Models\Pipeline::where('is_default', true)->first()
                ?? \App\Modules\Pipeline\Models\Pipeline::firstOrFail();
            $validated['pipeline_id'] = $defaultPipeline->id;
        }

        if (empty($validated['pipeline_stage_id'])) {
            $firstStage = \App\Modules\Pipeline\Models\PipelineStage::where('pipeline_id', $validated['pipeline_id'])
                ->orderBy('order_index')
                ->firstOrFail();
            $validated['pipeline_stage_id'] = $firstStage->id;
        } else {
            \App\Modules\Pipeline\Models\PipelineStage::where('pipeline_id', $validated['pipeline_id'])
                ->where('id', $validated['pipeline_stage_id'])
                ->firstOrFail();
        }

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $cfValidator->validate(
                'deal',
                $validated['custom_fields'],
                (int) $tenantContext->getTenantId(),
            );
        }

        $validated['stage_entered_at'] = now();
        $validated['amount'] = $validated['amount'] ?? 0;
        $validated['currency'] = strtoupper($validated['currency'] ?? 'USD');

        $deal = Deal::create($validated);

        return response()->json([
            'message' => 'Deal created.',
            'data' => $deal->load(['pipelineStage', 'contact']),
        ], 201);
    }

    public function showJson(Deal $deal): JsonResponse
    {
        Gate::authorize('view', $deal);

        return response()->json([
            'data' => $deal->load(['pipeline', 'pipelineStage', 'contact', 'account', 'assignedUser']),
        ]);
    }

    public function update(
        Request $request,
        Deal $deal,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('update', $deal);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'assigned_user_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::organizationMemberExists((int) $deal->organization_id)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($request->exists('custom_fields')) {
            $merged = array_merge(
                is_array($deal->custom_fields) ? $deal->custom_fields : [],
                is_array($validated['custom_fields'] ?? null) ? $validated['custom_fields'] : [],
            );
            $validated['custom_fields'] = $cfValidator->validate(
                'deal',
                $merged,
                (int) $deal->organization_id,
            );
        }

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $deal->update($validated);

        return response()->json([
            'message' => 'Deal updated.',
            'data' => $deal->fresh(['pipelineStage', 'contact']),
        ]);
    }

    public function transitionStage(
        Request $request,
        Deal $deal,
        DealStageTransitionService $transitionService,
    ): JsonResponse {
        Gate::authorize('update', $deal);

        $validated = $request->validate([
            'pipeline_stage_id' => ['required', 'integer'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newStage = PipelineStage::query()
            ->where('pipeline_id', $deal->pipeline_id)
            ->findOrFail($validated['pipeline_stage_id']);

        $deal = $transitionService->transition(
            $deal,
            $newStage,
            $validated['lost_reason'] ?? null,
            $request->user(),
        );

        return response()->json([
            'message' => 'Deal stage updated.',
            'data' => $deal,
        ]);
    }

    public function destroy(Deal $deal): JsonResponse
    {
        Gate::authorize('delete', $deal);

        $deal->delete();

        return response()->json(['message' => 'Deal deleted.']);
    }
}
