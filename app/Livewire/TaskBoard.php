<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Traits\AuthorizesResourceAccess;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class TaskBoard extends Component
{
    use AuthorizesResourceAccess;

    protected $rules = [
        'projectId' => 'nullable|integer|exists:projects,id',
        'assigneeFilter' => 'nullable|integer|exists:users,id',
    ];

    public ?int $projectId = null;

    public array $projects = [];

    public array $columns = [];

    public ?int $assigneeFilter = null;

    public array $assignees = [];

    public function mount(): void
    {
        $this->projects = Project::select('id', 'name', 'slug')
            ->orderBy('name')
            ->get()
            ->toArray();

        if (count($this->projects) > 0) {
            $this->projectId = $this->projects[0]['id'];
            $this->loadBoard();
        }
    }

    public function updatedProjectId(): void
    {
        $this->assigneeFilter = null;
        $this->loadBoard();
    }

    public function updatedAssigneeFilter(): void
    {
        $this->loadBoard();
    }

    public function moveTask(int $taskId, string $status): void
    {
        $this->authorizeResourceAccess('manage_projects', '您没有管理项目任务的权限。');

        $validStatuses = ['todo', 'in_progress', 'review', 'done', 'blocked'];
        if (! in_array($status, $validStatuses)) {
            session()->flash('error', '无效的任务状态');

            return;
        }

        Task::where('id', $taskId)->update(['status' => $status]);
        $this->loadBoard();
    }

    protected function loadBoard(): void
    {
        if (! $this->projectId) {
            return;
        }

        $project = Project::with(['tasks.assignee'])->findOrFail($this->projectId);
        $tasks = $project->tasks;

        // Apply assignee filter
        if ($this->assigneeFilter) {
            $tasks = $tasks->where('assignee_id', $this->assigneeFilter);
        }

        // Build assignee list for filter dropdown
        $this->assignees = User::whereHas('tasks', fn ($q) => $q->where('project_id', $this->projectId))
            ->select('id', 'name')
            ->get()
            ->toArray();

        // Group by status
        $statuses = ['todo', 'in_progress', 'review', 'done', 'blocked'];
        $this->columns = [];

        foreach ($statuses as $s) {
            /** @var Collection<int, Task> $colTasks */
            $colTasks = $tasks->where('status', $s)->values();
            $this->columns[$s] = $colTasks->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'estimate' => $t->estimate,
                'spent' => $t->spent,
                'due_at' => $t->due_at?->format('m/d'),
                'is_overdue' => $t->is_overdue,
                'assignee' => $t->assignee ? [
                    'id' => $t->assignee->id,
                    'name' => $t->assignee->name,
                ] : null,
            ])->toArray();
        }
    }

    public function render()
    {
        return view('livewire.task-board')
            ->layout('layouts.app')
            ->title('看板 — Project Monitor');
    }
}
