<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;

/**
 * 日报生成服务
 *
 * 从数据库聚合项目/任务/风险数据，生成结构化日报
 */
class DailyReportService
{
    protected string $date;

    public function __construct(?string $date = null)
    {
        $this->date = $date ?? now()->toDateString();
    }

    /**
     * 生成完整日报数据
     */
    public function generate(): array
    {
        return [
            'date' => $this->date,
            'summary' => $this->buildSummary(),
            'projects' => $this->buildProjectDetails(),
            'risks' => $this->buildRiskAlerts(),
            'tasks_new' => $this->getNewTasks(),
            'tasks_completed' => $this->getCompletedTasks(),
        ];
    }

    /**
     * 全局概要统计
     */
    protected function buildSummary(): array
    {
        $projects = Project::where('status', 'active')->count();

        $tasks = Task::query();
        $todo = (clone $tasks)->where('status', 'todo')->count();
        $inProgress = (clone $tasks)->where('status', 'in_progress')->count();
        $done = (clone $tasks)->where('status', 'done')->count();
        $overdue = Task::where('status', '!=', 'done')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->count();

        $risks = Risk::where('status', 'open')->count();
        $critical = Risk::where('status', 'open')->where('severity', 'critical')->count();

        return [
            'active_projects' => $projects,
            'todo_tasks' => $todo,
            'in_progress_tasks' => $inProgress,
            'done_tasks' => $done,
            'overdue_tasks' => $overdue,
            'total_tasks' => $todo + $inProgress + $done,
            'open_risks' => $risks,
            'critical_risks' => $critical,
        ];
    }

    /**
     * 各项目详情
     */
    protected function buildProjectDetails(): array
    {
        return Project::withCount([
            'tasks',
            'tasks as done_count' => fn ($q) => $q->where('status', 'done'),
            'tasks as in_progress_count' => fn ($q) => $q->where('status', 'in_progress'),
            'tasks as todo_count' => fn ($q) => $q->where('status', 'todo'),
        ])
            ->orderBy('status')
            ->orderBy('name')
            ->get()
            /** @var Project $p */
            ->map(fn (Project $p) => [
                'name' => $p->name,
                'slug' => $p->slug,
                'status' => $p->status,
                'total' => $p->tasks_count,
                'done' => $p->done_count,
                'in_progress' => $p->in_progress_count,
                'todo' => $p->todo_count,
                'completion_rate' => $p->tasks_count > 0
                    ? round(($p->done_count / $p->tasks_count) * 100)
                    : 0,
            ])
            ->toArray();
    }

    /**
     * 风险预警列表
     */
    protected function buildRiskAlerts(): array
    {
        return Risk::where('status', 'open')
            ->with('project:id,name')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('last_seen_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn (Risk $r) => [
                'title' => $r->title,
                'severity' => $r->severity,
                'project_name' => ($r->project->name ?? '—'),
                'rule_key' => $r->rule_key,
                'detail' => $r->detail,
                'first_seen_at' => $r->first_seen_at->toDateTimeString(),
                'occurrence_count' => $r->occurrence_count,
            ])
            ->toArray();
    }

    /**
     * 今日新增任务
     */
    protected function getNewTasks(): array
    {
        return Task::whereDate('created_at', $this->date)
            ->with('project:id,name')
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => [
                'title' => $t->title,
                'project' => $t->project->name ?? '—',
                'priority' => $t->priority,
            ])
            ->toArray();
    }

    /**
     * 今日完成任务
     */
    protected function getCompletedTasks(): array
    {
        return Task::whereDate('completed_at', $this->date)
            ->where('status', 'done')
            ->with('project:id,name')
            ->limit(10)
            ->get()
            ->map(fn (Task $t) => [
                'title' => $t->title,
                'project' => $t->project->name ?? '—',
            ])
            ->toArray();
    }

    /**
     * 导出 Markdown 格式
     */
    public function toMarkdown(array $report): string
    {
        $s = $report['summary'];
        $md = "# 项目日报 — {$report['date']}\n\n";
        $md .= "## 📊 总览\n\n";
        $md .= "| 指标 | 数量 |\n";
        $md .= "|------|------|\n";
        $md .= "| 活跃项目 | {$s['active_projects']} |\n";
        $md .= "| 待办任务 | {$s['todo_tasks']} |\n";
        $md .= "| 进行中 | {$s['in_progress_tasks']} |\n";
        $md .= "| 已完成 | {$s['done_tasks']} |\n";
        $md .= "| 延期任务 | {$s['overdue_tasks']} |\n";
        $md .= "| 开放风险 | {$s['open_risks']} |\n";
        $md .= "| 严重风险 | {$s['critical_risks']} |\n\n";

        $md .= "## 📋 项目详情\n\n";
        foreach ($report['projects'] as $p) {
            $md .= "### {$p['name']}\n";
            $md .= "- 状态：{$p['status']} | 进度：{$p['completion_rate']}%\n";
            $md .= "- 任务：{$p['done']}/{$p['total']} 已完成\n\n";
        }

        if (! empty($report['risks'])) {
            $md .= "## ⚠️ 风险预警\n\n";
            foreach ($report['risks'] as $r) {
                $md .= "- **{$r['title']}**（{$r['severity']}）— {$r['project_name']}\n";
            }
            $md .= "\n";
        }

        $md .= "---\n*由 Project Monitor 自动生成 — ".now()->format('Y-m-d H:i')."*\n";

        return $md;
    }

    /**
     * 导出 HTML 格式
     */
    public function toHtml(array $report): string
    {
        $s = $report['summary'];

        return view('reports.daily-html', ['report' => $report, 'summary' => $s])->render();
    }
}
