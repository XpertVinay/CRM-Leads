<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Services\TenantProvisioningService;
use App\Modules\Tenancy\Support\OrganizationInputNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $organizations = $user->organizations()->orderBy('organizations.id')->get();
        $activeOrgId = (int) $request->session()->get('active_organization_id', 0);
        $activeOrganization = $organizations->firstWhere('id', $activeOrgId)
            ?? $organizations->firstWhere('status', 'active');

        if ($activeOrganization !== null && $activeOrgId === 0) {
            $request->session()->put('active_organization_id', (int) $activeOrganization->id);
        }

        return view('organizations.index', [
            'organizations' => $organizations,
            'activeOrganization' => $activeOrganization,
            'activeRole' => $activeOrganization ? $user->roleIn($activeOrganization) : null,
        ]);
    }

    public function store(Request $request, TenantProvisioningService $provisioning): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', Organization::class);

        $input = $request->all();
        ['slug' => $normalizedSlug, 'invalid_slug' => $slugNormalizedToEmpty] = OrganizationInputNormalizer::normalizeSlug(
            $input['slug'] ?? null,
        );
        $input['slug'] = $normalizedSlug;
        $input['registration_identifier'] = OrganizationInputNormalizer::normalizeNullableString(
            $input['registration_identifier'] ?? null,
        );
        $input['primary_contact_email'] = OrganizationInputNormalizer::normalizeNullableEmail(
            $input['primary_contact_email'] ?? null,
        );
        $input['timezone'] = OrganizationInputNormalizer::normalizeNullableString($input['timezone'] ?? null);
        $input['currency'] = OrganizationInputNormalizer::normalizeNullableString($input['currency'] ?? null);

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', 'unique:organizations,slug'],
            'registration_identifier' => ['nullable', 'string', 'max:100', 'unique:organizations,registration_identifier'],
            'primary_contact_email' => ['nullable', 'email', 'max:191', 'unique:organizations,primary_contact_email'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($slugNormalizedToEmpty): void {
            if ($slugNormalizedToEmpty) {
                $validator->errors()->add('slug', 'The slug must contain at least one alphanumeric character.');
            }
        });

        $validated = $validator->validate();

        $organization = $provisioning->provisionOrganization($request->user(), $validated);

        if ($request->hasSession()) {
            $request->session()->put('active_organization_id', (int) $organization->id);
        }

        if ($request->expectsJson() || $request->is('ajax/*')) {
            return response()->json([
                'message' => 'Organization created successfully.',
                'organization' => $organization,
            ], 201);
        }

        return redirect()->route('organizations.index')
            ->with('status', 'Organization created and activated.');
    }

    public function update(Request $request, TenantContext $tenantContext): JsonResponse|RedirectResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('update', $organization);

        $input = $request->all();
        $input['registration_identifier'] = OrganizationInputNormalizer::normalizeNullableString(
            $input['registration_identifier'] ?? null,
        );
        $input['primary_contact_email'] = OrganizationInputNormalizer::normalizeNullableEmail(
            $input['primary_contact_email'] ?? null,
        );

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'registration_identifier' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('organizations', 'registration_identifier')->ignore($organization->id),
            ],
            'primary_contact_email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('organizations', 'primary_contact_email')->ignore($organization->id),
            ],
            'timezone' => ['required', 'string', 'max:50', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ])->validate();

        $organization->update([
            'name' => $validated['name'],
            'registration_identifier' => $validated['registration_identifier'] ?? null,
            'primary_contact_email' => $validated['primary_contact_email'] ?? null,
            'timezone' => $validated['timezone'],
            'currency' => strtoupper($validated['currency']),
        ]);

        if ($request->expectsJson() || $request->is('ajax/*')) {
            return response()->json([
                'message' => 'Organization settings updated.',
                'organization' => $organization->fresh(),
            ]);
        }

        return redirect()->route('organizations.index')
            ->with('status', 'Organization settings updated.');
    }

    public function switch(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $organization = Organization::query()->find((int) $validated['organization_id']);

        if (! $organization instanceof Organization || ! $organization->isActive()) {
            return response()->json([
                'message' => 'Requested organization is unavailable or suspended.',
            ], 403);
        }

        if (! $user->isSuperadmin() && $user->roleIn($organization) === null) {
            return response()->json([
                'message' => 'You are not a member of the requested organization.',
            ], 403);
        }

        if ($request->hasSession()) {
            $request->session()->put('active_organization_id', (int) $organization->id);
        }

        $tenantContext->setTenant($organization);

        return response()->json([
            'message' => 'Active organization switched.',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $user->roleIn($organization),
            ],
        ]);
    }
}
