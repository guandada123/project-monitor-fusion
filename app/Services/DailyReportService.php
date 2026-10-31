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
            // ── 2026-10-31 新增：真实指标 ──
            // pmf 的 tasks / risks 表从来没有数据，上面四个指标恒为 0，
            // 日报每天推出去的信息量等于零（"9 项目 · 0 任务 · 0 风险"）。
            // 从这里开始补上采集快照里真正在动的东西：代码规模 / 提交活跃度 / CI 状态。
            ...$this->buildRealMetrics(),
        ];
    }

    /**
     * 从采集快照聚合的真实指标。
     *
     * 这些数字有实际数据源（各项目 meta 由 ops 采集器写入），
     * 与 tasks/risks 那套恒 0 的口径完全不同。
     *
     * @return array<string, int>
     */
    protected function buildRealMetrics(): array
    {
        $rows = Project::query()->get(['meta']);

        $loc = 0;
        $commits = 0;
        $ciRed = 0;
        for ($i = 0; $i < $rows->count(); $i++) {
            $p = $rows[$i];
            $loc += $p->code_loc;
            $commits += $p->commits7d;
            if ((bool) (is_array($p->meta) ? ($p->meta['ci_main_red'] ?? false) : false)) {
                $ciRed++;
            }
        }

        return [
            'total_loc' => $loc,
            'commits_7d' => $commits,
            'ci_red_projects' => $ciRed,
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
                // 2026-10-31：无任务时以代码规模/提交数代替「0%」，避免日报里
                // 每个项目都写成 "0% (0/0)"——那看起来像全线停滞，实际是没有任务数据。
                'has_task_data' => $p->has_task_data,
                'code_loc' => $p->code_loc,
                'commits_7d' => $p->commits7d,
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
        $md .= '| 代码规模 | '.number_format(($s['total_loc'] ?? 0) / 10000, 1)." 万行 |\n";
        $md .= '| 近 7 天提交 | '.($s['commits_7d'] ?? 0)." 次 |\n";
        $md .= '| 主分支 CI 红 | '.($s['ci_red_projects'] ?? 0)." 个项目 |\n";
        // 2026-10-31：任务/风险类指标在无数据时会全是 0，容易读成"全线停滞"，
        // 因此明确标注口径，让人知道这是"没有数据源"而非"没有进展"。
        $hasTaskData = ($s['total_tasks'] ?? 0) > 0;
        if ($hasTaskData) {
            $md .= "| 待办任务 | {$s['todo_tasks']} |\n";
            $md .= "| 进行中 | {$s['in_progress_tasks']} |\n";
            $md .= "| 已完成 | {$s['done_tasks']} |\n";
            $md .= "| 延期任务 | {$s['overdue_tasks']} |\n";
        } else {
            $md .= "| 任务指标 | 未接入（pmf 无任务数据源） |\n";
        }
        $md .= "| 开放风险 | {$s['open_risks']} |\n";
        $md .= "| 严重风险 | {$s['critical_risks']} |\n\n";

        $md .= "## 📋 项目详情\n\n";
        foreach ($report['projects'] as $p) {
            $md .= "### {$p['name']}\n";
            if (! empty($p['has_task_data'])) {
                $md .= "- 状态：{$p['status']} | 进度：{$p['completion_rate']}%\n";
                $md .= "- 任务：{$p['done']}/{$p['total']} 已完成\n\n";
            } else {
                $locRaw = (int) ($p['code_loc'] ?? 0);
                $loc = $locRaw > 0 ? number_format($locRaw / 10000, 1).' 万行' : '未采集';
                $md .= "- 状态：{$p['status']} | 代码：{$loc} | 近 7 天 {$p['commits_7d']} 次提交\n\n";
            }
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
