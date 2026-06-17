<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 */
class ReportCenter extends Component
{
    public string $reportType = 'weekly';
    public string $period = 'current';
    public array $selectedProjects = [];
    public bool $includeRisks = true;
    public bool $includeTasks = true;
    public bool $includeResources = true;

    public array $reportData = [];
    public bool $reportGenerated = false;

    public function mount()
    {
        $this->selectedProjects = Project::pluck('id')->toArray();
    }

    public function generate()
    {
        $this->reportData = $this->buildReport();
        $this->reportGenerated = true;
    }

    private function buildReport(): array
    {
        $projects = Project::with('owner')->whereIn('id', $this->selectedProjects)->get();
        $dateRange = $this->getDateRange();

        $data = [
            'title' => $this->reportType === 'weekly' ? '项目周报' : '月度汇总',
            'period' => $dateRange,
            'generated_at' => now()->format('Y-m-d H:i'),
            'projects' => [],
            'summary' => [
                'total_projects' => $projects->count(),
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'overdue_tasks' => 0,
                'active_risks' => 0,
                'severe_risks' => 0,
            ],
        ];

        foreach ($projects as $project) {
            $tasks = Task::where('project_id', $project->id)
                ->when($dateRange, function ($q) use ($dateRange) {
                    $q->whereBetween('created_at', $dateRange);
                })->get();

            $risks = Risk::where('project_id', $project->id)->get();

            $projectData = [
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'progress' => $project->progress_percent ?? 0,
                'owner' => ($project->owner->name ?? '未分配'),
                'task_stats' => [
                    'total' => $tasks->count(),
                    'todo' => $tasks->where('status', 'todo')->count(),
                    'in_progress' => $tasks->where('status', 'in_progress')->count(),
                    'review' => $tasks->where('status', 'review')->count(),
                    'done' => $tasks->where('status', 'done')->count(),
                    'overdue' => $tasks->filter(fn(\App\Models\Task $t) => $t->due_at && $t->due_at->isPast() && $t->status !== 'done')->count(),
                ],
                'risks' => $risks->map(fn($r) => [
                    'title' => $r->title,
                    'severity' => $r->severity,
                    'status' => $r->status,
                ])->toArray(),
            ];

            $data['projects'][] = $projectData;
            $data['summary']['total_tasks'] += $projectData['task_stats']['total'];
            $data['summary']['completed_tasks'] += $projectData['task_stats']['done'];
            $data['summary']['overdue_tasks'] += $projectData['task_stats']['overdue'];
            $data['summary']['active_risks'] += $risks->where('status', 'active')->count();
            $data['summary']['severe_risks'] += $risks->where('severity', 'severe')->count();
        }

        // Team workload
        $data['workload'] = [];
        if ($this->includeResources) {
            $users = \App\Models\User::withCount(['tasks' => fn($q) => $q->whereNotIn('status', ['done'])])->get();
            foreach ($users as $user) {
                $data['workload'][] = [
                    'name' => $user->name,
                    'active_tasks' => $user->tasks_count,
                    'workload_pct' => $user->workload_percent,
                ];
            }
        }

        return $data;
    }

    private function getDateRange(): array
    {
        $now = now();
        if ($this->reportType === 'weekly') {
            if ($this->period === 'last') {
                $start = $now->copy()->subWeek()->startOfWeek();
                $end = $now->copy()->subWeek()->endOfWeek();
            } else {
                $start = $now->copy()->startOfWeek();
                $end = $now;
            }
            return [$start, $end];
        } else {
            if ($this->period === 'last') {
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
            } else {
                $start = $now->copy()->startOfMonth();
                $end = $now;
            }
            return [$start, $end];
        }
    }

    public function exportMarkdown()
    {
        if (!$this->reportGenerated) {
            $this->generate();
        }

        $md = $this->renderMarkdown();
        $filename = Str::slug($this->reportData['title']) . '-' . now()->format('Ymd') . '.md';

        return response()->streamDownload(function () use ($md) {
            echo $md;
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function exportHtml()
    {
        if (!$this->reportGenerated) {
            $this->generate();
        }

        $html = $this->renderHtml();
        $filename = Str::slug($this->reportData['title']) . '-' . now()->format('Ymd') . '.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function renderMarkdown(): string
    {
        $d = $this->reportData;
        $lines = [];

        $lines[] = "# {$d['title']}";
        $lines[] = '';
        $lines[] = "**周期**: {$d['period'][0]->format('Y-m-d')} ~ {$d['period'][1]->format('Y-m-d')}";
        $lines[] = "**生成时间**: {$d['generated_at']}";
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 概览';
        $lines[] = '';
        $lines[] = "| 指标 | 数值 |";
        $lines[] = "|------|------|";
        $lines[] = "| 项目数 | {$d['summary']['total_projects']} |";
        $lines[] = "| 任务总数 | {$d['summary']['total_tasks']} |";
        $lines[] = "| 已完成 | {$d['summary']['completed_tasks']} |";
        $lines[] = "| 已延期 | {$d['summary']['overdue_tasks']} |";
        $lines[] = "| 活跃风险 | {$d['summary']['active_risks']} |";
        $lines[] = "| 严重风险 | {$d['summary']['severe_risks']} |";
        $lines[] = '';

        if (!empty($d['projects'])) {
            $lines[] = '## 项目详情';
            $lines[] = '';
            foreach ($d['projects'] as $p) {
                $lines[] = "### {$p['name']}";
                $lines[] = '';
                $lines[] = "- 状态: {$p['status']} · 进度: {$p['progress']}% · 负责人: {$p['owner']}";
                $lines[] = "- 任务: 总计 {$p['task_stats']['total']} | 待办 {$p['task_stats']['todo']} | 进行中 {$p['task_stats']['in_progress']} | 已完成 {$p['task_stats']['done']} | 延期 {$p['task_stats']['overdue']}";
                if (!empty($p['risks'])) {
                    $lines[] = "- 风险:";
                    foreach ($p['risks'] as $r) {
                        $sev = ['low' => '🟢', 'medium' => '🟡', 'high' => '🟠', 'severe' => '🔴'][$r['severity']] ?? '⚪';
                        $lines[] = "  - {$sev} {$r['title']} ({$r['status']})";
                    }
                }
                $lines[] = '';
            }
        }

        if (!empty($d['workload'])) {
            $lines[] = '## 团队负载';
            $lines[] = '';
            $lines[] = "| 成员 | 活跃任务 | 负载率 |";
            $lines[] = "|------|----------|--------|";
            foreach ($d['workload'] as $w) {
                $lines[] = "| {$w['name']} | {$w['active_tasks']} | {$w['workload_pct']}% |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function renderHtml(): string
    {
        $md = $this->renderMarkdown();
        // Simple markdown to HTML conversion using Parsedown if available, or basic regex
        $html = nl2br(htmlspecialchars($md));
        return "<!DOCTYPE html><html lang=\"zh-CN\"><head><meta charset=\"utf-8\"><title>Project Monitor Report</title>
<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:800px;margin:0 auto;padding:2rem;line-height:1.6;color:#1a1a1a;}
h1{font-size:2rem;margin-bottom:.5rem;}h2{font-size:1.4rem;margin-top:2rem;border-bottom:2px solid #e2e8f0;padding-bottom:.5rem;}
h3{font-size:1.1rem;color:#475569;}table{border-collapse:collapse;width:100%;margin:1rem 0;}th,td{border:1px solid #e2e8f0;padding:.5rem;text-align:left;}
th{background:#f8fafc;font-weight:600;}ul{margin:.5rem 0;padding-left:1.5rem;}</style></head><body>{$html}</body></html>";
    }

    public function getProjectsProperty()
    {
        return Project::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.report-center', [
            'projects' => $this->projects,
        ])->layout('layouts.app')->title('报表中心 — Project Monitor');
    }
}
