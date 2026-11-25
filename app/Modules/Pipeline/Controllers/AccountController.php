<?php

namespace App\Modules\Pipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Account::class);

        return view('accounts.index');
    }

    public function show(Account $account): View
    {
        Gate::authorize('view', $account);

        $account->load(['contacts', 'deals.pipelineStage']);

        return view('accounts.show', ['account' => $account]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Account::class);

        $query = Account::query()
            ->withCount('contacts', 'deals')
            ->orderByDesc('id');

        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $needle = '%' . strtolower(trim($search)) . '%';
            $query->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(domain, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(industry, \'\')) LIKE ?', [$needle]);
            });
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('create', Account::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'domain' => ['nullable', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:100'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'billing_address' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $cfValidator->validate(
                'account',
                $validated['custom_fields'],
                (int) $tenantContext->getTenantId(),
            );
        }

        $account = Account::create($validated);

        return response()->json([
            'message' => 'Account created.',
            'data' => $account,
        ], 201);
    }

    public function showJson(Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        return response()->json([
            'data' => $account->load(['contacts', 'deals.pipelineStage']),
        ]);
    }

    public function update(
        Request $request,
        Account $account,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('update', $account);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'domain' => ['nullable', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:100'],
            'annual_revenue' => ['nullable', 'numeric', 'min:0'],
            'employee_count' => ['nullable', 'integer', 'min:1'],
            'billing_address' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($request->exists('custom_fields')) {
            $merged = array_merge(
                is_array($account->custom_fields) ? $account->custom_fields : [],
                is_array($validated['custom_fields'] ?? null) ? $validated['custom_fields'] : [],
            );
            $validated['custom_fields'] = $cfValidator->validate(
                'account', $merged, (int) $account->organization_id,
            );
        }

        $account->update($validated);

        return response()->json([
            'message' => 'Account updated.',
            'data' => $account->fresh(),
        ]);
    }

    public function destroy(Account $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        $account->delete();

        return response()->json(['message' => 'Account deleted.']);
    }
}
