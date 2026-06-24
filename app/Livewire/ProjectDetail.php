<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class ProjectDetail extends Component
{
    public Project $project;

    public array $taskDistribution = [];

    public array $ganttData = [];

    public array $riskTimeline = [];

    public function mount(string $slug): void
    {
        $this->project = Project::where('slug', $slug)
            ->with(['tasks.assignee', 'risks' => fn ($q) => $q->orderBy('last_seen_at', 'desc')->limit(20)])
            ->firstOrFail();

        $this->loadData();
    }

    protected function loadData(): void
    {
        // Task distribution by status
        $statuses = ['todo', 'in_progress', 'review', 'done', 'blocked'];
        $this->taskDistribution = collect($statuses)->map(fn ($s) => [
            'status' => $s,
            'count' => $this->project->tasks->where('status', $s)->count(),
        ])->toArray();

        // Gantt data
        /** @var Collection<int, Task> $tasks */
        $tasks = $this->project->tasks;
        /** @var Collection<int, Task> $filteredTasks */
        $filteredTasks = $tasks->filter(fn (Task $t) => $t->start_at || $t->due_at);
        $this->ganttData = $filteredTasks
            /** @phpstan-ignore-next-line Collection generics limitation with filter+map chain */
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'name' => \Str::limit($t->title, 25),
                'start' => ($t->start_at ?? $t->created_at)->timestamp * 1000,
                'end' => ($t->completed_at ?? $t->due_at ?? now())->timestamp * 1000,
                'status' => $t->status,
                'assignee' => $t->assignee->name ?? '未分配',
            ])->values()->toArray();

        // Risk timeline
        /** @var Collection<int, Risk> $risks */
        $risks = $this->project->risks;
        $this->riskTimeline = $risks
            ->map(fn (Risk $r) => [
                'title' => $r->title,
                'severity' => $r->severity,
                'date' => $r->first_seen_at->format('m/d'),
                'status' => $r->status,
            ])->toArray();
    }

    public function render()
    {
        return view('livewire.project-detail')
            ->layout('layouts.app')
            ->title($this->project->name.' — Project Monitor');
    }
}
