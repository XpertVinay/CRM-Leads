<?php

namespace App\Modules\Pipeline\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Services\CustomFieldValidationService;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Contact::class);

        return view('contacts.index');
    }

    public function show(Contact $contact): View
    {
        Gate::authorize('view', $contact);

        $contact->load(['account', 'deals.pipelineStage']);

        return view('contacts.show', ['contact' => $contact]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Contact::class);

        $query = Contact::query()
            ->with('account:id,name')
            ->withCount('deals')
            ->orderByDesc('id');

        $accountId = $request->query('account_id');
        if (is_string($accountId) && ctype_digit($accountId)) {
            $query->where('account_id', (int) $accountId);
        }

        $limit = min(max((int) $request->query('limit', 200), 1), 500);

        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $needle = Str::lower(trim($search));
            $contacts = $query->get()->filter(function (Contact $contact) use ($needle): bool {
                foreach ([$contact->first_name, $contact->last_name, $contact->email, $contact->phone] as $value) {
                    if (is_string($value) && $value !== '' && Str::contains(Str::lower($value), $needle)) {
                        return true;
                    }
                }

                return false;
            })->take($limit)->values();

            return response()->json(['data' => $contacts]);
        }

        return response()->json(['data' => $query->limit($limit)->get()]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('create', Contact::class);

        $tenantId = (int) $tenantContext->getTenantId();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'account_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('accounts', $tenantId)],
            'job_title' => ['nullable', 'string', 'max:100'],
            'preferred_channel' => ['nullable', 'string', Rule::in(Contact::PREFERRED_CHANNELS)],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $cfValidator->validate(
                'contact',
                $validated['custom_fields'],
                (int) $tenantContext->getTenantId(),
            );
        }

        $contact = Contact::create($validated);

        return response()->json([
            'message' => 'Contact created.',
            'data' => $contact,
        ], 201);
    }

    public function showJson(Contact $contact): JsonResponse
    {
        Gate::authorize('view', $contact);

        return response()->json([
            'data' => $contact->load(['account', 'deals.pipelineStage', 'touchpoints']),
        ]);
    }

    public function update(
        Request $request,
        Contact $contact,
        CustomFieldValidationService $cfValidator,
    ): JsonResponse {
        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'account_id' => ['nullable', 'integer', \App\Support\Validation\TenantValidationRules::existsInOrganization('accounts', (int) $contact->organization_id)],
            'job_title' => ['nullable', 'string', 'max:100'],
            'preferred_channel' => ['nullable', 'string', Rule::in(Contact::PREFERRED_CHANNELS)],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($request->exists('custom_fields')) {
            $merged = array_merge(
                is_array($contact->custom_fields) ? $contact->custom_fields : [],
                is_array($validated['custom_fields'] ?? null) ? $validated['custom_fields'] : [],
            );
            $validated['custom_fields'] = $cfValidator->validate(
                'contact',
                $merged,
                (int) $contact->organization_id,
            );
        }

        $contact->update($validated);

        return response()->json([
            'message' => 'Contact updated.',
            'data' => $contact->fresh(),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }
}
