<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomFields\Models\CustomFieldDefinition;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $organization = $tenantContext->getTenant();
        $user = $request->user();

        return view('dashboard', [
            'organization' => $organization,
            'role' => $user->roleIn($organization) ?? ($user->isSuperadmin() ? 'superadmin' : null),
            'membersCount' => $organization->users()->count(),
            'customFieldsCount' => CustomFieldDefinition::query()->count(),
        ]);
    }
}
