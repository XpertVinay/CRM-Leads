<?php

namespace App\Modules\Tenancy\Services;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\RbacMenu;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Models\RbacRole;
use App\Modules\Tenancy\Models\RbacUserPermission;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RbacService
{
    private const CACHE_TTL_SECONDS = 60;
    private const VERSION_CACHE_KEY = 'rbac:cache_version';

    /**
     * Request-level memoization to avoid repeated cache hits in a single request.
     *
     * @var array<string, array<int, string>>
     */
    private array $requestCache = [];

    public function hasPermission(User $user, Organization|int|null $organization, string $permissionSlug): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($organization === null) {
            return false;
        }

        $effective = $this->getEffectivePermissions($user, $organization);

        return in_array($permissionSlug, $effective, true);
    }

    /**
     * @return array<int, string>
     */
    public function getEffectivePermissions(User $user, Organization|int|null $organization): array
    {
        if ($user->isSuperadmin()) {
            return $this->getAllPermissionSlugs();
        }

        if ($organization === null) {
            return [];
        }

        $orgId = (int) ($organization instanceof Organization ? $organization->id : $organization);
        if ($orgId <= 0) {
            return [];
        }

        $roleSlug = $user->roleIn($orgId);
        if ($roleSlug === null) {
            return [];
        }

        $memoKey = $orgId . ':' . $user->id . ':' . $roleSlug;
        if (isset($this->requestCache[$memoKey])) {
            return $this->requestCache[$memoKey];
        }

        if (! $this->tablesExist()) {
            return [];
        }

        $this->ensureSeeded();

        $rolePermissions = $this->getRolePermissions($roleSlug);
        $overrides = $this->getUserOverrides($orgId, (int) $user->id);

        $effective = array_flip($rolePermissions);
        foreach ($overrides as $slug => $allowed) {
            if ($allowed) {
                $effective[$slug] = true;
            } else {
                unset($effective[$slug]);
            }
        }

        return $this->requestCache[$memoKey] = array_keys($effective);
    }

    /**
     * @return array<int, string>
     */
    public function getRolePermissions(string $roleSlug): array
    {
        $version = $this->getCacheVersion();
        $key = "rbac:v{$version}:role:{$roleSlug}";

        /** @var array<int, string> $permissions */
        $permissions = Cache::remember($key, self::CACHE_TTL_SECONDS, static function () use ($roleSlug): array {
            return DB::table('rbac_role_permission')
                ->join('rbac_roles', 'rbac_roles.id', '=', 'rbac_role_permission.role_id')
                ->join('rbac_permissions', 'rbac_permissions.id', '=', 'rbac_role_permission.permission_id')
                ->where('rbac_roles.slug', $roleSlug)
                ->pluck('rbac_permissions.slug')
                ->map(static fn ($slug): string => (string) $slug)
                ->values()
                ->all();
        });

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function getUserOverrides(int $organizationId, int $userId): array
    {
        $version = $this->getCacheVersion();
        $key = "rbac:v{$version}:user:{$organizationId}:{$userId}";

        /** @var array<string, bool> $overrides */
        $overrides = Cache::remember($key, self::CACHE_TTL_SECONDS, static function () use ($organizationId, $userId): array {
            $rows = DB::table('rbac_user_permissions')
                ->join('rbac_permissions', 'rbac_permissions.id', '=', 'rbac_user_permissions.permission_id')
                ->where('rbac_user_permissions.user_id', $userId)
                ->where(function ($q) use ($organizationId): void {
                    $q->where('rbac_user_permissions.organization_id', $organizationId)
                        ->orWhereNull('rbac_user_permissions.organization_id');
                })
                ->orderByRaw('CASE WHEN rbac_user_permissions.organization_id IS NULL THEN 0 ELSE 1 END ASC')
                ->get(['rbac_permissions.slug', 'rbac_user_permissions.allowed']);

            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row->slug] = (bool) $row->allowed;
            }

            return $map;
        });

        return $overrides;
    }

    /**
     * @return Collection<int, object>
     */
    private function getAllCachedMenus(): Collection
    {
        if (! Schema::hasTable('rbac_menus')) {
            return collect();
        }

        $this->ensureSeeded();
        $version = $this->getCacheVersion();

        /** @var array<int, array<string, mixed>> $rawMenus */
        $rawMenus = Cache::remember("rbac:v{$version}:all_menus", self::CACHE_TTL_SECONDS, static function (): array {
            return RbacMenu::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'label',
                    'route_name',
                    'route_pattern',
                    'icon',
                    'permission_slug',
                    'section',
                    'superadmin_only',
                    'requires_org',
                    'sort_order',
                    'is_active',
                ])
                ->map(static fn (RbacMenu $menu): array => [
                    'id' => (int) $menu->id,
                    'label' => (string) $menu->label,
                    'route_name' => (string) $menu->route_name,
                    'route_pattern' => (string) $menu->route_pattern,
                    'icon' => (string) $menu->icon,
                    'permission_slug' => $menu->permission_slug !== null ? (string) $menu->permission_slug : null,
                    'section' => (string) $menu->section,
                    'superadmin_only' => (bool) $menu->superadmin_only,
                    'requires_org' => (bool) $menu->requires_org,
                    'sort_order' => (int) $menu->sort_order,
                    'is_active' => (bool) $menu->is_active,
                ])
                ->all();
        });

        return collect($rawMenus)->map(static fn (array $row): object => (object) $row);
    }

    /**
     * @return Collection<int, object>
     */
    public function getSidebarMenus(User $user, ?Organization $organization): Collection
    {
        if (! Schema::hasTable('rbac_menus')) {
            return collect();
        }

        $isSuperadmin = $user->isSuperadmin();
        $effective = $organization !== null ? $this->getEffectivePermissions($user, $organization) : [];
        $whatsappConfigured = null;

        return $this->getAllCachedMenus()
            ->filter(function (object $menu) use ($isSuperadmin, $organization, $effective, &$whatsappConfigured): bool {
                return $this->isMenuAllowedForUser(
                    $menu,
                    $isSuperadmin,
                    $organization,
                    $effective,
                    $whatsappConfigured,
                );
            })
            ->values();
    }

    /**
     * Enforce portal menu visibility rules (active status, superadmin flag, permission slug, and WhatsApp setup)
     * on direct route requests matching configured menu items.
     */
    public function isRouteAllowedByMenus(string $routeName, User $user, ?Organization $organization): bool
    {
        if ($routeName === '' || ! Schema::hasTable('rbac_menus')) {
            return true;
        }

        $matchingMenus = $this->getAllCachedMenus()->filter(
            static fn (object $menu): bool => $menu->route_name === $routeName
        );

        if ($matchingMenus->isEmpty()) {
            return true;
        }

        $isSuperadmin = $user->isSuperadmin();
        $effective = $organization !== null ? $this->getEffectivePermissions($user, $organization) : [];
        $whatsappConfigured = null;

        foreach ($matchingMenus as $menu) {
            if ($this->isMenuAllowedForUser(
                $menu,
                $isSuperadmin,
                $organization,
                $effective,
                $whatsappConfigured,
                allowPipelinesViewFallback: true,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $effective
     */
    private function isMenuAllowedForUser(
        object $menu,
        bool $isSuperadmin,
        ?Organization $organization,
        array $effective,
        ?bool &$whatsappConfigured,
        bool $allowPipelinesViewFallback = false,
    ): bool {
        if (! $menu->is_active) {
            return false;
        }

        $isSuperadminMenu = $menu->superadmin_only
            || $menu->section === 'superadmin'
            || str_starts_with((string) $menu->route_name, 'superadmin.')
            || in_array($menu->route_name, ['organizations.index', 'coupons.index'], true);

        if ($isSuperadminMenu && ! $isSuperadmin) {
            return false;
        }

        if ($menu->requires_org && $organization === null) {
            return false;
        }

        if ($menu->route_name === 'whatsapp.chat') {
            if ($organization === null) {
                return false;
            }

            $whatsappConfigured ??= WhatsAppAccount::isConfiguredForOrganization((int) $organization->id);
            if (! $whatsappConfigured) {
                return false;
            }
        }

        if ($isSuperadmin) {
            return true;
        }

        if ($allowPipelinesViewFallback && $menu->route_name === 'pipelines.index' && $menu->permission_slug === 'pipelines.create') {
            return true;
        }

        if ($menu->permission_slug === null || $menu->permission_slug === '') {
            return true;
        }

        if ($organization === null) {
            return false;
        }

        return in_array($menu->permission_slug, $effective, true);
    }

    /**
     * @return Collection<int, RbacRole>
     */
    public function getAssignableRolesFor(User $actor): Collection
    {
        $this->ensureSeeded();

        $query = RbacRole::query()->orderBy('id');

        if (! $actor->isSuperadmin()) {
            $query->where('slug', '!=', TenantRole::OrgAdmin->value);
        }

        return $query->get();
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function syncRolePermissions(RbacRole $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
        $this->flushCache();
    }

    public function setUserOverride(?Organization $organization, User $user, int $permissionId, ?bool $allowed): void
    {
        if ($allowed === null) {
            RbacUserPermission::query()
                ->where('organization_id', $organization?->id)
                ->where('user_id', $user->id)
                ->where('permission_id', $permissionId)
                ->delete();
        } else {
            RbacUserPermission::query()->updateOrCreate(
                [
                    'organization_id' => $organization?->id,
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                ],
                [
                    'allowed' => $allowed,
                ]
            );
        }

        $this->flushCache();
    }

    public function flushCache(): void
    {
        $this->requestCache = [];
        $current = (int) Cache::get(self::VERSION_CACHE_KEY, 1);
        Cache::forever(self::VERSION_CACHE_KEY, $current + 1);
    }

    public function ensureSeeded(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        if (RbacRole::query()->count() === 0 || RbacPermission::query()->count() === 0) {
            RbacSeeder::seedDefaults();
        } elseif (Schema::hasTable('rbac_menus') && RbacMenu::query()->count() === 0) {
            RbacSeeder::seedMenus();
        }
    }

    /**
     * @return array<int, string>
     */
    private function getAllPermissionSlugs(): array
    {
        if (! $this->tablesExist()) {
            return array_column(RbacSeeder::definitions(), 'slug');
        }

        $this->ensureSeeded();
        $version = $this->getCacheVersion();

        /** @var array<int, string> $slugs */
        $slugs = Cache::remember("rbac:v{$version}:all_permissions", self::CACHE_TTL_SECONDS, static function (): array {
            return RbacPermission::query()
                ->orderBy('sort_order')
                ->pluck('slug')
                ->map(static fn ($slug): string => (string) $slug)
                ->all();
        });

        return $slugs;
    }

    private function getCacheVersion(): int
    {
        return (int) Cache::get(self::VERSION_CACHE_KEY, 1);
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('rbac_roles')
            && Schema::hasTable('rbac_permissions')
            && Schema::hasTable('rbac_role_permission')
            && Schema::hasTable('rbac_user_permissions');
    }
}
