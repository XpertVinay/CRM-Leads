<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Models\RbacRole;
use App\Modules\Tenancy\Models\RbacUserPermission;
use App\Modules\Tenancy\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RbacManagementController extends Controller
{
    public function __construct(
        private readonly RbacService $rbacService,
    ) {}

    public function index(Request $request): View
    {
        $this->rbacService->ensureSeeded();

        $roles = RbacRole::query()
            ->with('permissions:id,slug')
            ->orderBy('id')
            ->get();

        $permissionsByModule = RbacPermission::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('module');

        $organizations = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedOrgId = $request->integer('organization_id') ?: ($organizations->first()?->id ?? 0);
        $selectedOrg = $selectedOrgId > 0 ? Organization::query()->find($selectedOrgId) : null;

        $orgUsers = $selectedOrg
            ? $selectedOrg->users()
                ->get(['users.id', 'users.name', 'users.email'])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
            : collect();

        $selectedUserId = $request->integer('user_id') ?: ($orgUsers->first()?->id ?? 0);

        $userOverrides = ($selectedOrg && $selectedUserId > 0)
            ? RbacUserPermission::query()
                ->where('organization_id', $selectedOrg->id)
                ->where('user_id', $selectedUserId)
                ->pluck('allowed', 'permission_id')
                ->all()
            : [];

        return view('superadmin.rbac', [
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
            'organizations' => $organizations,
            'selectedOrg' => $selectedOrg,
            'orgUsers' => $orgUsers,
            'selectedUserId' => $selectedUserId,
            'userOverrides' => $userOverrides,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'unique:rbac_roles,slug'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:rbac_permissions,id'],
        ]);

        $slug = ! empty($validated['slug'])
            ? $validated['slug']
            : Str::snake(Str::ascii($validated['name']));

        if (RbacRole::query()->where('slug', $slug)->exists()) {
            return redirect()
                ->route('superadmin.rbac.index')
                ->withErrors(['slug' => "Role slug '{$slug}' already exists."]);
        }

        $role = RbacRole::create([
            'slug' => $slug,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $permissionIds = array_map('intval', $validated['permission_ids'] ?? []);
        $this->rbacService->syncRolePermissions($role, $permissionIds);

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Role '{$role->name}' created.");
    }

    public function updateRole(Request $request, RbacRole $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:rbac_permissions,id'],
        ]);

        if (array_key_exists('name', $validated)) {
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $role->description,
            ]);
        }

        if (array_key_exists('permission_ids', $validated) || ! array_key_exists('name', $validated)) {
            $permissionIds = array_map('intval', $validated['permission_ids'] ?? []);
            $this->rbacService->syncRolePermissions($role, $permissionIds);
        } else {
            $this->rbacService->flushCache();
        }

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Permissions updated for role '{$role->name}' and RBAC cache refreshed.");
    }

    public function destroyRole(RbacRole $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('superadmin.rbac.index')
                ->withErrors(['role' => "System role '{$role->name}' cannot be deleted."]);
        }

        $inUse = DB::table('organization_user')->where('role', $role->slug)->exists()
            || DB::table('organization_invitations')->whereNull('accepted_at')->where('role', $role->slug)->exists();

        if ($inUse) {
            return redirect()
                ->route('superadmin.rbac.index')
                ->withErrors(['role' => "Role '{$role->name}' is currently assigned to members or pending invitations."]);
        }

        $name = $role->name;
        $role->delete();
        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Role '{$name}' deleted.");
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/', 'unique:rbac_permissions,slug'],
            'name' => ['required', 'string', 'max:120'],
            'module' => ['required', 'string', 'max:64'],
            'is_menu_item' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        RbacPermission::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'module' => $validated['module'],
            'is_menu_item' => (bool) ($validated['is_menu_item'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Permission '{$validated['slug']}' created.");
    }

    public function updatePermission(Request $request, RbacPermission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/', Rule::unique('rbac_permissions', 'slug')->ignore($permission->id)],
            'name' => ['required', 'string', 'max:120'],
            'module' => ['required', 'string', 'max:64'],
            'is_menu_item' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $permission->update([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'module' => $validated['module'],
            'is_menu_item' => (bool) ($validated['is_menu_item'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Permission '{$permission->slug}' updated.");
    }

    public function destroyPermission(RbacPermission $permission): RedirectResponse
    {
        $slug = $permission->slug;
        $permission->delete();
        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', "Permission '{$slug}' deleted.");
    }

    public function updateUserOverrides(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['in:inherit,allow,deny'],
        ]);

        $organization = Organization::query()->findOrFail((int) $validated['organization_id']);
        $user = User::query()->findOrFail((int) $validated['user_id']);

        foreach ($validated['overrides'] ?? [] as $permissionId => $mode) {
            $allowed = match ($mode) {
                'allow' => true,
                'deny' => false,
                default => null,
            };

            $this->rbacService->setUserOverride($organization, $user, (int) $permissionId, $allowed);
        }

        return redirect()
            ->route('superadmin.rbac.index', [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ])
            ->with('status', "User permission overrides saved for {$user->name} and RBAC cache refreshed.");
    }

    public function flushCache(): RedirectResponse
    {
        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.rbac.index')
            ->with('status', 'RBAC permission cache flushed successfully.');
    }
}
