<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use App\Modules\Tenancy\Context\TenantContext;
use App\Support\Queries\SqlSearchHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        if (strlen($q) < 2) {
            return response()->json([
                'data' => [
                    'leads' => [],
                    'contacts' => [],
                    'deals' => [],
                    'campaigns' => [],
                ],
            ]);
        }

        $user = $request->user();
        $tenant = $tenantContext->getTenant();

        $searchLower = Str::lower($q);

        $leads = $user && $user->hasPermissionIn($tenant, 'leads.view')
            ? Lead::query()
                ->orderByDesc('id')
                ->get(['id', 'lead_reference', 'first_name', 'last_name', 'phone', 'company_name', 'status'])
                ->filter(function (Lead $l) use ($searchLower): bool {
                    foreach ([$l->lead_reference, $l->first_name, $l->last_name, $l->phone, $l->company_name] as $val) {
                        if (is_string($val) && $val !== '' && Str::contains(Str::lower($val), $searchLower)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->take(5)
                ->values()
                ->map(fn (Lead $l) => [
                    'id' => $l->id,
                    'title' => trim(($l->lead_reference ? '[' . $l->lead_reference . '] ' : '') . $l->fullName()),
                    'subtitle' => $l->phone ?: $l->status,
                    'url' => route('leads.show', $l),
                ])
            : collect();

        $contacts = $user && $user->hasPermissionIn($tenant, 'contacts.view')
            ? Contact::query()
                ->orderByDesc('id')
                ->get(['id', 'first_name', 'last_name', 'email', 'phone'])
                ->filter(function (Contact $c) use ($searchLower): bool {
                    foreach ([$c->first_name, $c->last_name, $c->email, $c->phone] as $val) {
                        if (is_string($val) && $val !== '' && Str::contains(Str::lower($val), $searchLower)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->take(5)
                ->values()
                ->map(fn (Contact $c) => [
                    'id' => $c->id,
                    'title' => trim($c->first_name . ' ' . ($c->last_name ?? '')),
                    'subtitle' => $c->email ?? '',
                    'url' => route('contacts.show', $c),
                ])
            : collect();

        $deals = $user && $user->hasPermissionIn($tenant, 'deals.view')
            ? SqlSearchHelper::applyCaseInsensitiveLike(
                Deal::query(),
                ['title'],
                $q,
            )
                ->limit(5)
                ->get(['id', 'title', 'amount', 'currency'])
                ->map(fn (Deal $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'subtitle' => $d->currency . ' ' . number_format((float) $d->amount, 2),
                    'url' => route('deals.show', $d),
                ])
            : collect();

        $campaigns = $user && $user->hasPermissionIn($tenant, 'campaigns.view')
            ? SqlSearchHelper::applyCaseInsensitiveLike(
                Campaign::query(),
                ['name'],
                $q,
            )
                ->limit(5)
                ->get(['id', 'name', 'platform', 'status'])
                ->map(fn (Campaign $c) => [
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => $c->platform . ' · ' . $c->status,
                    'url' => route('campaigns.show', $c),
                ])
            : collect();

        return response()->json([
            'data' => [
                'leads' => $leads,
                'contacts' => $contacts,
                'deals' => $deals,
                'campaigns' => $campaigns,
            ],
        ]);
    }
}
