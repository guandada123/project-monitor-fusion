<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Support\Carbon;

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
     * 日报的调度时间（HH:MM）。
     *
     * 2026-11-01：此前设置页能存 `feishu_daily_time` 也能回显，但 routes/console.php
     * 写死 `dailyAt('08:30')` —— 改时间不生效，典型「已配置 ≠ 被读取」。
     * 这里统一解析：读不到 / 值非法（含 25:99 这类）一律回落 08:30，
     * **绝不让一个配置问题把整个调度注册搞挂**（调度挂了是全线停摆，比时间错更严重）。
     */
    public static function scheduledTime(): string
    {
        $fallback = '08:30';

        try {
            $configured = (string) app(SettingsService::class)->getString('feishu_daily_time', $fallback);
        } catch (\Throwable) {
            // 迁移未跑 / DB 不可达 / settings 表还不存在
            return $fallback;
        }

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $configured) === 1 ? $configured : $fallback;
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
     * @return array{
     *     total_loc: int,
     *     commits_7d: int,
     *     ci_red_projects: int,
     *     ci_data_as_of: string|null,
     *     ci_data_age_hours: float|null,
     *     ci_data_in_future: bool
     * }
     */
    protected function buildRealMetrics(): array
    {
        // config 必须一起 select：snapshot_generated_at 写在 config 里，只取 meta 永远读不到。
        $rows = Project::query()->get(['meta', 'config']);

        $loc = 0;
        $commits = 0;
        $ciRed = 0;
        $latestCollectedAt = null;
        for ($i = 0; $i < $rows->count(); $i++) {
            $p = $rows[$i];
            $loc += $p->code_loc;
            $commits += $p->commits7d;
            $meta = is_array($p->meta) ? $p->meta : [];
            if ((bool) ($meta['ci_main_red'] ?? false)) {
                $ciRed++;
            }

            // ── 2026-11-05：新鲜度判据必须是「采集时点」，不是「CI 最近一次运行」 ──
            // 踩过的坑：原实现取 meta.ci_last_run_at（被观测对象最后一次事件时间）。
            // CI 只在 push 时触发，每天 03:00 快照跑完到 08:30 都没有新 run，
            // 于是 2h 阈值下日报连续 3 天写「CI 数据已陈旧」—— 而采集器 07:20 刚刷过、
            // 导入每 30 分钟一次，数据其实是新的。狼来了叫三次就没人看了。
            // 正确判据：ops:import-snapshot 写入的 config.snapshot_generated_at。
            // 采集器若真挂掉，这个值会停在原地并持续增长 → 2h 阈值照常报警。
            $cfg = is_array($p->config) ? $p->config : [];
            $collectedAt = $cfg['snapshot_generated_at'] ?? null;
            if (is_string($collectedAt) && $collectedAt !== '') {
                try {
                    $ts = Carbon::parse($collectedAt);
                } catch (\Throwable) {
                    $ts = null;
                }
                if ($ts !== null && ($latestCollectedAt === null || $ts->gt($latestCollectedAt))) {
                    $latestCollectedAt = $ts;
                }
            }
        }

        // 只报数字不报数据时点，等于把有延迟的数说成实时事实（2026-11-01 实例：
        // Claw 两次 CI failure 期间日报仍报「CI 红 0 个」—— 算法没错，是快照旧）。
        //
        // 2026-11-14 修边界：Carbon 3 的 diffInMinutes() 在「目标在未来」时返回**负数**
        // （实测 2 小时后 → -120 分钟，即 -2.0 小时），而陈旧判定是 `age > 阈值` ——
        // 负值永远不触发，等于哨兵静默失聪。本项目确实发生过时区事故（容器 TZ=UTC +
        // naive now() 导致落库日期错一天），所以不能假设时间戳一定可信：
        // 未来一律按 0 小时处理，并单独打标记，让文案说出「时点异常」而不是「距今 -2 小时」。
        $ciInFuture = false;
        if ($latestCollectedAt === null) {
            $ciAgeHours = null;
        } else {
            $rawAgeHours = $latestCollectedAt->diffInMinutes(now()) / 60;
            $ciInFuture = $rawAgeHours < 0;
            $ciAgeHours = round(max(0.0, $rawAgeHours), 1);
        }

        return [
            'total_loc' => $loc,
            'commits_7d' => $commits,
            'ci_red_projects' => $ciRed,
            'ci_data_as_of' => $latestCollectedAt?->toIso8601String(),
            'ci_data_age_hours' => $ciAgeHours,
            'ci_data_in_future' => $ciInFuture,
        ];
    }

    /**
     * CI 行的人话后缀：数据截至何时、是否已陈旧。
     *
     * 命令行表格与 Markdown 共用这一处文案，避免两边说法不一致。
     */
    public function ciFreshnessNote(array $summary): string
    {
        $asOf = $summary['ci_data_as_of'] ?? null;
        $ageHours = $summary['ci_data_age_hours'] ?? null;

        if (! is_string($asOf) || $ageHours === null) {
            return '（无 CI 数据）';
        }

        try {
            $label = Carbon::parse($asOf)->timezone(config('app.timezone'))->format('m-d H:i');
        } catch (\Throwable) {
            $label = $asOf;
        }

        // 时点落在未来 = 采集器时钟/时区异常（本项目发生过 TZ=UTC 导致落库错天）。
        // 这种数据不能按「距今 0 小时 = 最新」呈现，否则只是把「静默失聪」换成了
        // 「假新鲜」—— 同样危险，所以显式说异常。
        if (($summary['ci_data_in_future'] ?? false) === true) {
            return "（数据时点 {$label} 晚于当前时间，疑似采集器时钟/时区异常，该值不可信）";
        }

        $note = "（数据截至 {$label}，距今 {$ageHours} 小时）";
        // fallback 必须与 config/ops.php（默认 2）及导入端 OpsImportSnapshotCommand
        // （默认 2）一致：三处若出现 6 vs 2，config 缓存过期即让告警延迟 3 倍。
        $staleHours = (int) config('ops.ci_stale_hours', 2);
        if ($ageHours > $staleHours) {
            $note .= " ⚠️ CI 数据已陈旧（＞{$staleHours}h），该值仅供参考";
        }

        return $note;
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
        $md .= '| 主分支 CI 红 | '.($s['ci_red_projects'] ?? 0).' 个项目'.$this->ciFreshnessNote($s)." |\n";
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
