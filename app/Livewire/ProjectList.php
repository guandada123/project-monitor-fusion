<?php

namespace App\Livewire;

use App\Models\Project;
use Livewire\Component;

class ProjectList extends Component
{
    public function render()
    {
        return view('livewire.project-list', [
            // done_tasks_count 供 completion_rate 访问器零查询计算（否则每项目 2 条 count）
            'projects' => Project::withCount([
                'tasks',
                'risks',
                'tasks as done_tasks_count' => fn ($q) => $q->whereIn('status', Project::COMPLETED_STATUSES),
            ])
                ->orderBy('status')
                ->orderBy('created_at', 'desc')
                ->get(),
        ])->layout('layouts.app')->title('项目列表 — Project Monitor');
    }
}
