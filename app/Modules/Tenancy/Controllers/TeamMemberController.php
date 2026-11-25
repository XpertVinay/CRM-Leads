<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $members = $organization->users()->orderBy('users.name')->get();
        $invitations = OrganizationInvitation::query()
            ->whereNull('accepted_at')
            ->orderByDesc('id')
            ->get();
        $tokens = ApiToken::query()
            ->with('user')
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get();

        return view('settings.team', [
            'organization' => $organization,
            'members' => $members,
            'invitations' => $invitations,
            'tokens' => $tokens,
            'roles' => TenantRole::cases(),
        ]);
    }

    public function invite(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role' => ['required', 'string', Rule::in(TenantRole::values())],
        ]);

        $email = Str::lower(trim($validated['email']));

        $alreadyMember = $organization->users()
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->exists();

        if ($alreadyMember) {
            return response()->json([
                'message' => 'This user is already a member of the organization.',
            ], 422);
        }

        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $request->user()->id,
            'email' => $email,
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'message' => 'Invitation created successfully.',
            'invitation' => $invitation,
            'accept_url' => route('invitations.show', ['token' => $invitation->token]),
        ], 201);
    }

    public function store(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role' => ['required', 'string', Rule::in(TenantRole::values())],
            'name' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User) {
            if (empty($validated['name']) || empty($validated['password'])) {
                return response()->json([
                    'message' => 'Name and password are required to provision a new user.',
                ], 422);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $validated['password'],
            ]);
        }

        if ($user->roleIn($organization) !== null) {
            return response()->json([
                'message' => 'User is already a member of this organization.',
            ], 422);
        }

        $organization->users()->attach($user->id, ['role' => $validated['role']]);

        return response()->json([
            'message' => 'Team member added.',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $validated['role'],
            ],
        ], 201);
    }

    public function updateRole(Request $request, User $user, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $currentRole = $user->roleIn($organization);
        if ($currentRole === null) {
            abort(404, 'Member not found in active organization.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(TenantRole::values())],
        ]);

        $result = DB::transaction(function () use ($organization, $user, $validated): array {
            $membership = DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return ['status' => 404];
            }

            if ($membership->role === TenantRole::OrgAdmin->value && $validated['role'] !== TenantRole::OrgAdmin->value) {
                $adminCount = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('role', TenantRole::OrgAdmin->value)
                    ->lockForUpdate()
                    ->count();

                if ($adminCount <= 1) {
                    return [
                        'status' => 422,
                        'message' => 'Cannot demote the last organization administrator.',
                    ];
                }
            }

            $organization->users()->updateExistingPivot($user->id, [
                'role' => $validated['role'],
            ]);

            return ['status' => 200];
        });

        if ($result['status'] === 404) {
            abort(404, 'Member not found in active organization.');
        }

        if ($result['status'] === 422) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'Member role updated.',
            'user_id' => $user->id,
            'role' => $validated['role'],
        ]);
    }

    public function destroy(User $user, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $result = DB::transaction(function () use ($organization, $user): array {
            $membership = DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return ['status' => 404];
            }

            if ($membership->role === TenantRole::OrgAdmin->value) {
                $adminCount = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('role', TenantRole::OrgAdmin->value)
                    ->lockForUpdate()
                    ->count();

                if ($adminCount <= 1) {
                    return [
                        'status' => 422,
                        'message' => 'Cannot remove the last organization administrator.',
                    ];
                }
            }

            $organization->users()->detach($user->id);

            return ['status' => 200];
        });

        if ($result['status'] === 404) {
            abort(404, 'Member not found in active organization.');
        }

        if ($result['status'] === 422) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => 'Member removed from organization.',
        ]);
    }
}
