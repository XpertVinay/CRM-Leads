<?php

namespace App\Modules\Tenancy\Livewire;

use App\Models\Organization;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    public ?int $activeOrganizationId = null;

    public function mount(TenantContext $tenantContext): void
    {
        $this->activeOrganizationId = $tenantContext->getTenantId()
            ?? (session()->has('active_organization_id') ? (int) session('active_organization_id') : null);
    }

    public function switchOrganization(int $organizationId, TenantContext $tenantContext): mixed
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $organization = Organization::query()->find($organizationId);
        if (! $organization instanceof Organization || ! $organization->isActive()) {
            abort(403, 'Organization is not active.');
        }

        if (! $user->isSuperadmin() && $user->roleIn($organization) === null) {
            abort(403, 'You do not belong to the requested organization.');
        }

        session(['active_organization_id' => (int) $organization->id]);
        $tenantContext->setTenant($organization);
        $this->activeOrganizationId = (int) $organization->id;

        return $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        $user = Auth::user();
        $organizations = $user
            ? ($user->isSuperadmin()
                ? Organization::query()->where('status', 'active')->orderBy('id')->get()
                : $user->organizations()->where('organizations.status', 'active')->orderBy('organizations.id')->get())
            : collect();

        return view('livewire.tenancy.organization-switcher', [
            'organizations' => $organizations,
            'user' => $user,
        ]);
    }
}
