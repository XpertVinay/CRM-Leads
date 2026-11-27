<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\ApiToken;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use App\Modules\Tenancy\Services\RbacService;
use App\Support\Security\DataEncrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly RbacService $rbacService,
    ) {}

    public function index(Request $request, TenantContext $tenantContext): View
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $this->rbacService->ensureSeeded();
        $members = $organization->users()
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $invitations = OrganizationInvitation::query()
            ->whereNull('accepted_at')
            ->orderByDesc('id')
            ->get();
        $tokens = ApiToken::query()
            ->with('user')
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get();

        $assignableRoles = $this->rbacService->getAssignableRolesFor($request->user());

        return view('settings.team', [
            'organization' => $organization,
            'members' => $members,
            'invitations' => $invitations,
            'tokens' => $tokens,
            'roles' => $assignableRoles,
        ]);
    }

    public function invite(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $organization = $tenantContext->getTenant();
        Gate::authorize('manageTeam', $organization);

        $this->rbacService->ensureSeeded();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role' => ['required', 'string', Rule::exists('rbac_roles', 'slug')],
        ]);

        if (! $request->user()->isSuperadmin() && $validated['role'] === TenantRole::OrgAdmin->value) {
            return response()->json([
                'message' => 'Only superadmin can invite or assign the Organization Admin role.',
            ], 403);
        }

        $email = Str::lower(trim($validated['email']));
        $emailHash = DataEncrypter::instance()->blindIndex($email, 'email');

        $alreadyMember = $organization->users()
            ->where('users.email_hash', $emailHash)
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

        $this->rbacService->ensureSeeded();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role' => ['required', 'string', Rule::exists('rbac_roles', 'slug')],
            'name' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (! $request->user()->isSuperadmin() && $validated['role'] === TenantRole::OrgAdmin->value) {
            return response()->json([
                'message' => 'Only superadmin can assign the Organization Admin role.',
            ], 403);
        }

        $email = Str::lower(trim($validated['email']));
        $emailHash = DataEncrypter::instance()->blindIndex($email, 'email');
        $user = User::query()->where('email_hash', $emailHash)->first();

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

        $organization->users()->attach($user->id, [
            'role' => $validated['role'],
            'invited_by_user_id' => $request->user()->id,
        ]);

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

        $this->rbacService->ensureSeeded();

        $currentRole = $user->roleIn($organization);
        if ($currentRole === null) {
            abort(404, 'Member not found in active organization.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('rbac_roles', 'slug')],
        ]);

        $actor = $request->user();
        if (! $actor->isSuperadmin() && $validated['role'] === TenantRole::OrgAdmin->value) {
            return response()->json([
                'message' => 'Only superadmin can assign the Organization Admin role.',
            ], 403);
        }

        $result = DB::transaction(function () use ($organization, $user, $validated, $actor): array {
            $membership = DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return ['status' => 404];
            }

            if (! $actor->isSuperadmin()) {
                if ($membership->role === TenantRole::OrgAdmin->value) {
                    return [
                        'status' => 403,
                        'message' => 'Organization admins cannot modify another Organization Admin role.',
                    ];
                }

                if ((int) ($membership->invited_by_user_id ?? 0) !== (int) $actor->id) {
                    return [
                        'status' => 403,
                        'message' => 'You can only update roles for employees you personally invited.',
                    ];
                }
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

        if ($result['status'] === 403) {
            return response()->json([
                'message' => $result['message'],
            ], 403);
        }

        if ($result['status'] === 422) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        $user->clearRoleCache($organization);
        $this->rbacService->flushCache();

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

        $user->clearRoleCache($organization);

        return response()->json([
            'message' => 'Member removed from organization.',
        ]);
    }
}
