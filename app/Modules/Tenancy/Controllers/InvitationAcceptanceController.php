<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Modules\Tenancy\Scopes\OrganizationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        $invitation = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('token', $token)
            ->firstOrFail();

        return view('auth.accept-invitation', [
            'invitation' => $invitation,
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $invitation = OrganizationInvitation::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return $this->errorResponse($request, $token, 'Invitation has already been accepted.', 422, 'invitation');
        }

        if ($invitation->isExpired()) {
            return $this->errorResponse($request, $token, 'Invitation has expired.', 410, 'invitation');
        }

        $organization = $invitation->organization;
        if (! $organization || ! $organization->isActive()) {
            return $this->errorResponse($request, $token, 'Organization is not active.', 403, 'invitation');
        }

        $user = $request->user();

        if ($user instanceof User) {
            if (Str::lower($user->email) !== Str::lower($invitation->email)) {
                return $this->errorResponse(
                    $request,
                    $token,
                    'Invitation email does not match the authenticated user.',
                    403,
                    'email',
                );
            }
        } else {
            $existing = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])
                ->first();

            if ($existing instanceof User) {
                $credentials = $request->validate([
                    'password' => ['required', 'string'],
                ]);

                if (! Auth::attempt(['email' => $existing->email, 'password' => $credentials['password']])) {
                    return $this->errorResponse(
                        $request,
                        $token,
                        'Invalid credentials for existing account.',
                        403,
                        'password',
                    );
                }

                if ($request->hasSession()) {
                    $request->session()->regenerate();
                }

                $user = Auth::user();
            } else {
                $validated = $request->validate([
                    'name' => ['required', 'string', 'max:191'],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                ]);

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => Str::lower($invitation->email),
                    'password' => $validated['password'],
                ]);

                Auth::login($user);

                if ($request->hasSession()) {
                    $request->session()->regenerate();
                }
            }
        }

        DB::transaction(function () use ($invitation, $organization, $user): void {
            if ($user->roleIn($organization) === null) {
                $organization->users()->attach($user->id, [
                    'role' => $invitation->role,
                ]);
            }

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);
        });

        if ($request->hasSession()) {
            $request->session()->put('active_organization_id', (int) $organization->id);
        }

        if ($request->expectsJson() || $request->is('ajax/*')) {
            return response()->json([
                'message' => 'Invitation accepted.',
                'organization_id' => $organization->id,
                'role' => $invitation->role,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('status', "Joined {$organization->name} as {$invitation->role}.");
    }

    private function errorResponse(
        Request $request,
        string $token,
        string $message,
        int $status,
        string $errorKey,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson() || $request->is('ajax/*')) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()
            ->back(302, [], route('invitations.show', ['token' => $token]))
            ->withErrors([$errorKey => $message]);
    }
}
