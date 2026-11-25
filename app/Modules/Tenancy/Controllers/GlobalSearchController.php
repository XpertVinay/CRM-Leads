<?php

namespace App\Modules\Tenancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Pipeline\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => [
                'leads' => [],
                'contacts' => [],
                'deals' => [],
                'campaigns' => [],
            ]]);
        }

        $needle = '%'.Str::lower($q).'%';

        $leads = Lead::query()
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(COALESCE(lead_reference, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(company_name, \'\')) LIKE ?', [$needle]);
            })
            ->limit(5)
            ->get(['id', 'lead_reference', 'first_name', 'last_name', 'phone', 'status'])
            ->map(fn (Lead $l) => [
                'id' => $l->id,
                'title' => trim(($l->lead_reference ? '['.$l->lead_reference.'] ' : '').$l->fullName()),
                'subtitle' => $l->phone ?: $l->status,
                'url' => route('leads.show', $l),
            ]);

        $contacts = Contact::query()
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$needle]);
            })
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'title' => trim($c->first_name.' '.($c->last_name ?? '')),
                'subtitle' => $c->email ?? '',
                'url' => route('contacts.show', $c),
            ]);

        $deals = Deal::query()
            ->whereRaw('LOWER(title) LIKE ?', [$needle])
            ->limit(5)
            ->get(['id', 'title', 'amount', 'currency'])
            ->map(fn (Deal $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'subtitle' => $d->currency.' '.number_format((float) $d->amount, 2),
                'url' => route('deals.show', $d),
            ]);

        $campaigns = Campaign::query()
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->limit(5)
            ->get(['id', 'name', 'platform', 'status'])
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->platform.' · '.$c->status,
                'url' => route('campaigns.show', $c),
            ]);

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
