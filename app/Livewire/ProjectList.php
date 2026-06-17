<?php

namespace App\Livewire;

use App\Models\Project;
use Livewire\Component;

class ProjectList extends Component
{
    public function render()
    {
        return view('livewire.project-list', [
            'projects' => Project::withCount(['tasks', 'risks'])
                ->orderBy('status')
                ->orderBy('created_at', 'desc')
                ->get(),
        ])->layout('layouts.app')->title('项目列表 — Project Monitor');
    }
}
