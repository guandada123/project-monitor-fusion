<?php

namespace App\Livewire;

use App\Models\Project;
use Livewire\Attributes\Url;
use Livewire\Component;

class AggregateView extends Component
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    public string $sortField = 'completion_rate';

    public string $sortDirection = 'desc';

    /** @var array<string, string> */
    public array $sortOptions = [];

    public function mount(): void
    {
        $this->sortOptions = [
            'completion_rate' => '完成率',
            'risks_count' => '风险数',
            'tasks_count' => '任务数',
            'name' => '项目名称',
            'created_at' => '创建时间',
        ];
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function render()
    {
        $query = Project::with(['tasks', 'risks' => function ($q) {
            $q->whereIn('status', ['open', 'acknowledged']);
        }]);

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $projects = $query->get();

        // Compute aggregate stats
        $totalProjects = $projects->count();
        $totalTasks = $projects->sum(fn ($p) => $p->tasks->count());
        $completedTasks = $projects->sum(fn ($p) => $p->tasks->where('status', 'done')->count());
        $totalRisks = $projects->sum(fn ($p) => $p->risks->count());
        $criticalRisks = $projects->sum(fn ($p) => $p->risks->where('severity', 'critical')->count());
        $avgCompletion = $totalProjects > 0
            ? round($projects->avg(fn ($p) => $p->completion_rate), 1)
            : 0;

        // Enrich project data for the view
        $enrichedProjects = $projects->map(function ($project) {
            $riskCount = $project->risks->count();

            return (object) [
                'slug' => $project->slug,
                'name' => $project->name,
                'status' => $project->status,
                'source' => $project->source,
                'tasks_count' => $project->tasks->count(),
                'completed_tasks' => $project->tasks->where('status', 'done')->count(),
                'risks_count' => $riskCount,
                'critical_risks' => $project->risks->where('severity', 'critical')->count(),
                'high_risks' => $project->risks->where('severity', 'high')->count(),
                'completion_rate' => $project->completion_rate,
                'health' => $this->computeHealth($riskCount, $project->completion_rate),
                'created_at' => $project->created_at,
            ];
        });

        // Sort
        $sortField = $this->sortField;
        $sortDir = $this->sortDirection;
        $enrichedProjects = $enrichedProjects->sortBy(function ($p) use ($sortField) {
            $val = $p->$sortField ?? '';

            return is_numeric($val) ? (float) $val : strtolower($val);
        }, SORT_REGULAR, $sortDir === 'desc')->values();

        return view('livewire.aggregate-view', [
            'projects' => $enrichedProjects,
            'totalProjects' => $totalProjects,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'totalRisks' => $totalRisks,
            'criticalRisks' => $criticalRisks,
            'avgCompletion' => $avgCompletion,
        ])->layout('layouts.app')->title('项目聚合视图 — Project Monitor');
    }

    private function computeHealth(int $riskCount, float $completionRate): string
    {
        if ($riskCount === 0 && $completionRate >= 80) {
            return 'healthy';
        }
        if ($riskCount <= 2 || $completionRate >= 50) {
            return 'warning';
        }

        return 'critical';
    }
}
