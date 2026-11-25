<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Services\TenantProvisioningService;
use App\Modules\Tenancy\Support\OrganizationInputNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => Str::lower(trim($request->input('email'))),
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $firstActiveOrg = $user->organizations()
            ->where('organizations.status', 'active')
            ->orderBy('organizations.id')
            ->first();

        if ($firstActiveOrg !== null) {
            $request->session()->put('active_organization_id', (int) $firstActiveOrg->id);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->intended(route('organizations.index'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => Str::lower(trim($request->input('email'))),
            ]);
        }

        $input = $request->all();

        ['slug' => $normalizedSlug, 'invalid_slug' => $slugNormalizedToEmpty] = OrganizationInputNormalizer::normalizeSlug(
            $input['organization_slug'] ?? null,
        );
        $input['organization_slug'] = $normalizedSlug;
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
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_name' => ['required', 'string', 'max:191'],
            'organization_slug' => ['nullable', 'string', 'max:191', 'unique:organizations,slug'],
            'registration_identifier' => ['nullable', 'string', 'max:100', 'unique:organizations,registration_identifier'],
            'primary_contact_email' => ['nullable', 'email', 'max:191', 'unique:organizations,primary_contact_email'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone:all'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($slugNormalizedToEmpty): void {
            if ($slugNormalizedToEmpty) {
                $validator->errors()->add(
                    'organization_slug',
                    'The organization slug must contain at least one alphanumeric character.',
                );
            }
        });

        $validated = $validator->validate();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $primaryContactEmail = $validated['primary_contact_email'] ?? null;
        if ($primaryContactEmail === null) {
            $emailCandidate = $validated['email'];
            if (! Organization::withTrashed()->where('primary_contact_email', $emailCandidate)->exists()) {
                $primaryContactEmail = $emailCandidate;
            }
        }

        $organization = $provisioning->provisionOrganization($user, [
            'name' => $validated['organization_name'],
            'slug' => $validated['organization_slug'] ?? null,
            'registration_identifier' => $validated['registration_identifier'] ?? null,
            'primary_contact_email' => $primaryContactEmail,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'currency' => $validated['currency'] ?? 'USD',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('active_organization_id', (int) $organization->id);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        Auth::logout();
        $tenantContext->clear();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
