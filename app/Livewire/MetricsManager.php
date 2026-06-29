<?php

namespace App\Livewire;

use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Project;
use App\Services\MetricEngine;
use App\Traits\AuthorizesResourceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MetricsManager extends Component
{
    use AuthorizesResourceAccess;

    public string $slug;

    /** @var int 快照历史最大条数，可通过 Livewire 属性绑定动态配置 */
    public int $historyLimit = 30;

    public ?Project $project = null;

    // 新建/编辑表单
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formName = '';

    public string $formKey = '';

    public string $formType = 'count_tasks';

    public array $formStatuses = [];

    public array $formPriorities = [];

    public array $formSeverities = [];

    public array $formDoneStatuses = ['done', 'closed', 'completed'];

    public array $formWipStatuses = ['in_progress', 'doing'];

    public string $formExpression = '';

    public string $formSchedule = '';

    public string $formLabel = '';

    public string $formUnit = '';

    // 快照历史
    public bool $showHistory = false;

    public ?int $historyMetricId = null;

    public array $historySnapshots = [];

    protected $queryString = [];

    public function mount(string $slug): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $this->slug = $slug;
        $this->project = Project::where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        $metrics = Metric::where('project_id', $this->project->id)->get();

        return view('livewire.metrics-manager', [
            'metrics' => $metrics,
            'statusOptions' => ['open', 'in_progress', 'doing', 'done', 'closed', 'completed', 'cancelled'],
            'priorityOptions' => ['low', 'medium', 'high', 'critical'],
            'severityOptions' => ['low', 'medium', 'high', 'critical'],
            'metricTypes' => [
                'count_tasks' => '📊 任务计数',
                'completion_rate' => '📈 完成率',
                'wip_count' => '🔄 在制品',
                'count_risks' => '⚠️ 风险计数',
                'cycle_days' => '⏱ 平均周期',
                'formula' => '🧮 自定义公式',
            ],
        ])->layout('layouts.app');
    }

    // ── 新建指标 ──────────────────────────────────────

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $metric = $this->project->metrics()->findOrFail($id);
        $def = $metric->definition ?: [];

        $this->editingId = $id;
        $this->formName = $metric->name;
        $this->formKey = $metric->key;
        $this->formType = $def['type'] ?? 'count_tasks';
        $this->formSchedule = $metric->schedule_cron ?? '';
        $this->formLabel = $def['label'] ?? '';
        $this->formUnit = $def['unit'] ?? '';
        $this->formStatuses = $def['statuses'] ?? [];
        $this->formPriorities = $def['priorities'] ?? [];
        $this->formSeverities = $def['severities'] ?? [];
        $this->formDoneStatuses = $def['done_statuses'] ?? ['done', 'closed', 'completed'];
        $this->formWipStatuses = $def['wip_statuses'] ?? ['in_progress', 'doing'];
        $this->formExpression = $def['expression'] ?? '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function saveMetric(): void
    {
        $this->authorizeResourceAccess('manage_metrics');

        $validMetricTypes = [
            'count_tasks', 'completion_rate', 'wip_count',
            'count_risks', 'cycle_days', 'formula',
        ];

        $this->validate([
            'formName' => 'required|string|max:100',
            'formKey' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('metrics', 'key')
                    ->where('project_id', $this->project->id)
                    ->ignore($this->editingId),
            ],
            'formType' => ['required', 'string', Rule::in($validMetricTypes)],
        ]);

        $definition = $this->buildDefinition();

        try {
            DB::transaction(function () use ($definition) {
                if ($this->editingId) {
                    Metric::where('id', $this->editingId)->update([
                        'name' => $this->formName,
                        'key' => $this->formKey,
                        'definition' => $definition,
                        'schedule_cron' => $this->formSchedule ?: null,
                    ]);
                    session()->flash('message', '指标已更新');
                } else {
                    Metric::create([
                        'project_id' => $this->project->id,
                        'name' => $this->formName,
                        'key' => $this->formKey,
                        'kind' => 'custom',
                        'definition' => $definition,
                        'schedule_cron' => $this->formSchedule ?: null,
                    ]);
                    session()->flash('message', '自定义指标已创建');
                }
            });
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', '指标保存失败：'.$e->getMessage());

            return;
        }

        $this->resetForm();
    }

    public function deleteMetric(int $id): void
    {
        $this->authorizeResourceAccess('manage_metrics');
        $metric = $this->project->metrics()->findOrFail($id);
        if ($metric->kind === 'system') {
            session()->flash('error', '系统指标不可删除');

            return;
        }
        $metric->delete();
        session()->flash('message', '指标已删除');
    }

    // ── 计算 ──────────────────────────────────────────

    public function computeNow(): void
    {
        $this->authorizeResourceAccess('manage_metrics');

        // 频率限制：30 秒内不可重复计算，防止滥用或意外重复触发
        $lastSnapshot = MetricSnapshot::whereIn(
            'metric_id',
            $this->project->metrics()->pluck('id')
        )
            ->orderBy('captured_at', 'desc')
            ->first();

        if ($lastSnapshot && $lastSnapshot->captured_at->diffInSeconds(now()) < 30) {
            session()->flash('error', '计算过于频繁，请 30 秒后再试');

            return;
        }

        $engine = app(MetricEngine::class);
        $engine->computeForProject($this->project);
        session()->flash('message', '✅ 所有指标已重新计算并存入快照');
    }

    public function showSnapshots(int $metricId): void
    {
        // 确保指标属于当前项目，防止跨项目窥探快照数据
        $this->project->metrics()->findOrFail($metricId);

        $engine = app(MetricEngine::class);
        $this->historySnapshots = $engine->getSnapshots($metricId, $this->historyLimit);
        $this->historyMetricId = $metricId;
        $this->showHistory = true;
    }

    public function closeHistory(): void
    {
        $this->showHistory = false;
        $this->historyMetricId = null;
        $this->historySnapshots = [];
    }

    // ── 辅助 ──────────────────────────────────────────

    private function buildDefinition(): array
    {
        $base = ['type' => $this->formType];

        if ($this->formLabel) {
            $base['label'] = $this->formLabel;
        }
        if ($this->formUnit) {
            $base['unit'] = $this->formUnit;
        }

        return match ($this->formType) {
            'count_tasks' => array_merge($base, [
                'statuses' => $this->formStatuses,
                'priorities' => $this->formPriorities,
            ]),
            'count_risks' => array_merge($base, [
                'statuses' => $this->formStatuses,
                'severities' => $this->formSeverities,
            ]),
            'completion_rate' => array_merge($base, [
                'done_statuses' => $this->formDoneStatuses,
            ]),
            'wip_count' => array_merge($base, [
                'wip_statuses' => $this->formWipStatuses,
            ]),
            'cycle_days' => array_merge($base, [
                'done_statuses' => $this->formDoneStatuses,
            ]),
            'formula' => array_merge($base, [
                'expression' => $this->formExpression,
            ]),
            default => $base,
        };
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formName = '';
        $this->formKey = '';
        $this->formType = 'count_tasks';
        $this->formStatuses = [];
        $this->formPriorities = [];
        $this->formSeverities = [];
        $this->formDoneStatuses = ['done', 'closed', 'completed'];
        $this->formWipStatuses = ['in_progress', 'doing'];
        $this->formExpression = '';
        $this->formSchedule = '';
        $this->formLabel = '';
        $this->formUnit = '';
    }
}
