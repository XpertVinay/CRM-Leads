<?php

namespace App\Modules\Pipeline\Livewire;

use App\Modules\Pipeline\Models\Account;
use Illuminate\Support\Facades\Gate;
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

        if (trim($this->search) !== '') {
            $needle = '%' . strtolower(trim($this->search)) . '%';
            $query->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(domain, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(industry, \'\')) LIKE ?', [$needle]);
            });
        }

        return view('livewire.pipeline.account-list', [
            'accounts' => $query->paginate(20),
        ]);
    }
}
