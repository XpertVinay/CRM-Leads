<?php

namespace App\Modules\Pipeline\Livewire;

use App\Modules\Pipeline\Models\Account;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class AccountList extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', Account::class);

        $query = Account::query()
            ->withCount('contacts', 'deals')
            ->orderByDesc('id');

        $searchTerm = Str::lower(trim($this->search));
        if ($searchTerm !== '') {
            $filtered = $query->get()->filter(function (Account $account) use ($searchTerm): bool {
                foreach ([$account->name, $account->domain, $account->industry] as $value) {
                    if (is_string($value) && $value !== '' && Str::contains(Str::lower($value), $searchTerm)) {
                        return true;
                    }
                }

                return false;
            })->values();

            $perPage = 20;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $accounts = new LengthAwarePaginator(
                $filtered->forPage($page, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        } else {
            $accounts = $query->paginate(20);
        }

        return view('livewire.pipeline.account-list', [
            'accounts' => $accounts,
        ]);
    }
}
