<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\RbacMenu;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RbacMenuController extends Controller
{
    public function __construct(
        private readonly RbacService $rbacService,
    ) {}

    public function index(): View
    {
        $this->rbacService->ensureSeeded();

        $menus = RbacMenu::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $permissions = RbacPermission::query()
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get(['id', 'slug', 'name', 'module']);

        return view('superadmin.menus', [
            'menus' => $menus,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'route_name' => ['required', 'string', 'max:120'],
            'route_pattern' => ['nullable', 'string', 'max:120'],
            'icon' => ['required', 'string', 'max:64'],
            'permission_slug' => ['nullable', 'string', 'max:100'],
            'section' => ['required', 'in:main,pipeline,superadmin'],
            'superadmin_only' => ['nullable', 'boolean'],
            'requires_org' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RbacMenu::create([
            'label' => $validated['label'],
            'route_name' => $validated['route_name'],
            'route_pattern' => $validated['route_pattern'] ?: $validated['route_name'],
            'icon' => $validated['icon'],
            'permission_slug' => $validated['permission_slug'] ?: null,
            'section' => $validated['section'],
            'superadmin_only' => $validated['section'] === 'superadmin' || (bool) ($validated['superadmin_only'] ?? false),
            'requires_org' => (bool) ($validated['requires_org'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.menus.index')
            ->with('status', "Menu '{$validated['label']}' created.");
    }

    public function update(Request $request, RbacMenu $menu): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'route_name' => ['required', 'string', 'max:120'],
            'route_pattern' => ['nullable', 'string', 'max:120'],
            'icon' => ['required', 'string', 'max:64'],
            'permission_slug' => ['nullable', 'string', 'max:100'],
            'section' => ['required', 'in:main,pipeline,superadmin'],
            'superadmin_only' => ['nullable', 'boolean'],
            'requires_org' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $menu->update([
            'label' => $validated['label'],
            'route_name' => $validated['route_name'],
            'route_pattern' => $validated['route_pattern'] ?: $validated['route_name'],
            'icon' => $validated['icon'],
            'permission_slug' => $validated['permission_slug'] ?: null,
            'section' => $validated['section'],
            'superadmin_only' => $validated['section'] === 'superadmin' || (bool) ($validated['superadmin_only'] ?? false),
            'requires_org' => (bool) ($validated['requires_org'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.menus.index')
            ->with('status', "Menu '{$menu->label}' updated.");
    }

    public function destroy(RbacMenu $menu): RedirectResponse
    {
        $label = $menu->label;
        $menu->delete();
        $this->rbacService->flushCache();

        return redirect()
            ->route('superadmin.menus.index')
            ->with('status', "Menu '{$label}' deleted.");
    }
}
