<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Livewire\Component;

class GlobalSearch extends Component
{
    /** @var string 搜索关键词 */
    public string $query = '';

    /** @var bool 下拉面板是否打开 */
    public bool $show = false;

    /** @var int 键盘选中的索引 */
    public int $selectedIndex = 0;

    /** @var array 搜索结果缓存 */
    public array $results = [];

    /** @var int|null 选中搜索结果的 ID */
    public ?int $selectedId = null;

    /** @var array 各类型的数量统计 */
    public array $counts = [];

    /** @var bool 搜索中状态 */
    public bool $searching = false;

    public function mount(): void
    {
        $this->resetSearch();
    }

    /**
     * 搜索关键词变更时触发
     */
    public function updatedQuery(): void
    {
        $this->selectedIndex = 0;
        $this->searching = true;

        if (mb_strlen(trim($this->query)) < 1) {
            $this->results = [];
            $this->counts = [];
            $this->show = false;
            $this->searching = false;

            return;
        }

        $this->performSearch();
        $this->show = count($this->results) > 0;
        $this->searching = false;
    }

    /**
     * 执行跨表搜索
     *
     * 使用 LIKE 模糊搜索，配合 pg_trgm GIN 索引大幅加速中文模糊匹配。
     * PostgreSQL 下 LIKE '%关键词%' 可通过三元组（trigram）索引走索引扫描，
     * 无需全表扫描，对中文项目名、任务标题搜索性能提升显著。
     */
    protected function performSearch(): void
    {
        $q = trim($this->query);
        $like = '%'.$q.'%';

        // 搜索项目
        $projects = Project::where('name', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->select('id', 'name', 'slug', 'status', 'description')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => 'project',
                'title' => $p->name,
                'subtitle' => $p->slug,
                'description' => $p->description,
                'url' => "/projects/{$p->slug}",
                'badge' => $p->status,
            ]);

        // 搜索任务
        $tasks = Task::with('project:id,name,slug')
            ->where('title', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->select('id', 'project_id', 'title', 'status', 'priority', 'due_at')
            ->limit(5)
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'type' => 'task',
                'title' => $t->title,
                'subtitle' => $t->project->name ?? '无项目',
                'url' => $t->project->slug ? "/projects/{$t->project->slug}" : '#',
                'badge' => $t->status,
                'meta' => $t->priority,
            ]);

        // 搜索风险
        $risks = Risk::with('project:id,name,slug')
            ->where('title', 'like', $like)
            ->orWhere('detail', 'like', $like)
            ->select('id', 'project_id', 'title', 'severity', 'status', 'rule_key')
            ->limit(5)
            ->get()
            ->map(fn (Risk $r) => [
                'id' => $r->id,
                'type' => 'risk',
                'title' => $r->title,
                'subtitle' => ($r->project->name ?? '无项目'),
                'url' => '/risks',
                'badge' => $r->severity,
                'meta' => $r->rule_key,
            ]);

        $all = collect()
            ->merge($projects)
            ->merge($tasks)
            ->merge($risks)
            ->values()
            ->toArray();

        $this->results = $all;
        $this->counts = [
            'projects' => $projects->count(),
            'tasks' => $tasks->count(),
            'risks' => $risks->count(),
        ];
    }

    /**
     * 选中一条结果（键盘回车或点击）
     */
    public function selectResult(int $index): void
    {
        if (! isset($this->results[$index])) {
            return;
        }

        $result = $this->results[$index];
        $this->resetSearch();

        $this->redirect($result['url'], navigate: true);
    }

    /**
     * 键盘导航 Down
     */
    public function selectNext(): void
    {
        $count = count($this->results);
        if ($count === 0) {
            return;
        }

        $this->selectedIndex = ($this->selectedIndex + 1) % $count;
    }

    /**
     * 键盘导航 Up
     */
    public function selectPrev(): void
    {
        $count = count($this->results);
        if ($count === 0) {
            return;
        }

        $this->selectedIndex = ($this->selectedIndex - 1 + $count) % $count;
    }

    /**
     * 关闭搜索面板
     */
    public function closeSearch(): void
    {
        $this->show = false;
    }

    /**
     * 重置搜索状态
     */
    public function resetSearch(): void
    {
        $this->query = '';
        $this->results = [];
        $this->counts = [];
        $this->show = false;
        $this->selectedIndex = 0;
    }

    public function render()
    {
        return view('livewire.global-search');
    }
}
