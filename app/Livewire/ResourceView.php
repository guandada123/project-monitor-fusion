<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class ResourceView extends Component
{
    public function render()
    {
        $today = now()->toDateString();

        return view('livewire.resource-view', [
            'members' => User::withCount([
                'tasks as pending_tasks' => fn($q) => $q->pending(),
                'tasks as overdue_tasks' => fn($q) => $q->pending()->overdue(),
            ])
                ->withSum(['tasks as estimate_sum' => fn($q) => $q->whereNotIn('status', ['done'])], 'estimate')
                ->get()
                ->map(fn(\App\Models\User $u) => [
                    'name' => $u->name,
                    'workload' => min(round(((float) ($u->estimate_sum ?? 0)) / 40 * 100, 1), 200),
                    'pending' => $u->pending_tasks,
                    'overdue' => $u->overdue_tasks,
                ]),
        ])->layout('layouts.app')->title('资源视图 — Project Monitor');
    }
}
