<?php

namespace App\Livewire;

use App\Models\Metric;
use App\Models\Project;
use App\Traits\AuthorizesResourceAccess;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * @property-read Collection<int, Metric> $metrics
 * @property-read Collection<int, Project> $projects
 */
class MetricBuilder extends Component
{
    use AuthorizesResourceAccess;

    public bool $showModal = false;

    public ?int $editingId = null;

    // Form
    public string $formKey = '';

    public string $formName = '';

    public string $formKind = 'custom';

    public ?int $formProjectId = null;

    public string $formDefinition = '';

    public string $formScheduleCron = '0 8 * * *'; // Daily at 8am

    // Filter
    public string $filterKind = '';

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $metric = Metric::findOrFail($id);
        $this->editingId = $id;
        $this->formKey = $metric->key;
        $this->formName = $metric->name;
        $this->formKind = $metric->kind;
        $this->formProjectId = $metric->project_id;
        $this->formDefinition = json_encode($metric->definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->formScheduleCron = $metric->schedule_cron ?? '0 8 * * *';
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->formKey = '';
        $this->formName = '';
        $this->formKind = 'custom';
        $this->formProjectId = null;
        $this->formDefinition = json_encode([
            'operator' => 'ratio',
            'numerator' => ['field' => 'tasks_done'],
            'denominator' => ['field' => 'tasks_total'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->formScheduleCron = '0 8 * * *';
        $this->resetValidation();
    }

    public function save()
    {
        $this->authorizeResourceAccess('manage_metrics');
        $this->validate([
            'formKey' => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'formName' => 'required|string|max:255',
            'formKind' => 'required|in:system,custom',
        ]);

        // Validate JSON definition
        $definition = json_decode($this->formDefinition, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('formDefinition', 'JSON 格式无效: '.json_last_error_msg());

            return;
        }

        if ($this->editingId) {
            $metric = Metric::findOrFail($this->editingId);
            $metric->update([
                'key' => $this->formKey,
                'name' => $this->formName,
                'kind' => $this->formKind,
                'project_id' => $this->formProjectId,
                'definition' => $definition,
                'schedule_cron' => $this->formScheduleCron,
            ]);
            session()->flash('message', '指标已更新');
        } else {
            Metric::create([
                'key' => $this->formKey,
                'name' => $this->formName,
                'kind' => $this->formKind,
                'project_id' => $this->formProjectId,
                'definition' => $definition,
                'schedule_cron' => $this->formScheduleCron,
            ]);
            session()->flash('message', '指标已创建');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $metric = Metric::findOrFail($id);
        if ($metric->kind === 'system') {
            session()->flash('error', '系统指标不可删除');

            return;
        }
        $metric->delete();
        session()->flash('message', '指标已删除');
    }

    public function computeNow(int $id): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $metric = Metric::findOrFail($id);
        // Simulate computation
        $metric->update([
            'last_computed_at' => now(),
            'current_value' => rand(60, 95) + (rand(0, 40) / 10),
        ]);
        session()->flash('message', "指标 {$metric->name} 已重新计算");
    }

    public function getMetricsProperty()
    {
        return Metric::with('project')
            ->when($this->filterKind, fn ($q) => $q->where('kind', $this->filterKind))
            ->orderBy('kind')
            ->orderBy('name')
            ->get();
    }

    public function getProjectsProperty()
    {
        return Project::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.metric-builder', [
            'metrics' => $this->metrics,
            'projects' => $this->projects,
        ])->layout('layouts.app')->title('指标构建器 — Project Monitor');
    }
}
