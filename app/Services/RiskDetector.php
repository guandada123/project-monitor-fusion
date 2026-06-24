<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 风险自动检测引擎
 *
 * 支持 6 条内置规则，自动扫描并创建/更新风险记录
 */
class RiskDetector
{
    /**
     * 检测规则定义
     */
    const RULES = [
        'overdue_tasks' => [
            'title' => '存在延期未完成任务',
            'detail' => '项目中有超过截止日期但未完成的任务，可能导致里程碑延误。',
            'severity' => 'high',
            'threshold' => 1,
        ],
        'high_overdue_ratio' => [
            'title' => '延期任务占比过高',
            'detail' => '项目延期任务占比超过阈值，整体进度风险上升。',
            'severity' => 'high',
            'threshold' => 0.3, // 30%
        ],
        'no_recent_activity' => [
            'title' => '项目长期无活跃',
            'detail' => '项目在过去 7 天内没有任何任务更新，可能已停滞。',
            'severity' => 'medium',
            'threshold' => 7, // days
        ],
        'team_overload' => [
            'title' => '团队成员任务过载',
            'detail' => '成员被分配了过多进行中的任务，可能导致交付质量下降。',
            'severity' => 'medium',
            'threshold' => 5, // max tasks per person
        ],
        'unassigned_tasks' => [
            'title' => '存在未分配任务',
            'detail' => '项目中有任务没有分配给任何人，可能被遗漏。',
            'severity' => 'low',
            'threshold' => 1,
        ],
        'stale_in_progress' => [
            'title' => '任务长期处于进行中',
            'detail' => '有任务超过 14 天仍处于"进行中"状态，可能需要关注。',
            'severity' => 'medium',
            'threshold' => 14, // days
        ],
    ];

    /**
     * 执行全部风险检测
     */
    public function scanAll(): array
    {
        $results = [
            'total_detected' => 0,
            'new' => 0,
            'updated' => 0,
            'resolved' => 0,
            'by_rule' => [],
        ];

        // 按项目检测
        $projects = Project::whereIn('status', ['active', 'pending'])->get();
        foreach ($projects as $project) {
            foreach (self::RULES as $ruleKey => $ruleDef) {
                try {
                    $result = $this->detect($project, $ruleKey, $ruleDef);
                    if ($result['action'] === 'create') {
                        $results['new']++;
                        $results['by_rule'][$ruleKey] = ($results['by_rule'][$ruleKey] ?? 0) + 1;
                    } elseif ($result['action'] === 'update') {
                        $results['updated']++;
                        $results['by_rule'][$ruleKey] = ($results['by_rule'][$ruleKey] ?? 0) + 1;
                    } elseif ($result['action'] === 'resolve') {
                        $results['resolved']++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("RiskDetector rule {$ruleKey} failed for project {$project->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $results['total_detected'] = $results['new'] + $results['updated'];

        return $results;
    }

    /**
     * 执行单条规则检测
     */
    protected function detect(Project $project, string $ruleKey, array $ruleDef): array
    {
        $triggered = false;
        $evidence = [];

        switch ($ruleKey) {
            case 'overdue_tasks':
                $count = Task::where('project_id', $project->id)
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now()->toDateString())
                    ->count();
                $triggered = $count >= $ruleDef['threshold'];
                $evidence = ['overdue_count' => $count];
                break;

            case 'high_overdue_ratio':
                $total = Task::where('project_id', $project->id)->count();
                if ($total > 0) {
                    $overdue = Task::where('project_id', $project->id)
                        ->where('status', '!=', 'done')
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now()->toDateString())
                        ->count();
                    $ratio = $overdue / $total;
                    $triggered = $ratio >= $ruleDef['threshold'];
                    $evidence = ['total_tasks' => $total, 'overdue' => $overdue, 'ratio' => round($ratio, 2)];
                }
                break;

            case 'no_recent_activity':
                $latest = Task::where('project_id', $project->id)
                    ->max('updated_at');
                if ($latest) {
                    $daysSince = now()->diffInDays($latest, true);
                    $triggered = $daysSince >= $ruleDef['threshold'];
                    $evidence = ['last_activity' => $latest, 'days_since' => $daysSince];
                }
                break;

            case 'team_overload':
                $overloaded = DB::table('assignments')
                    ->join('tasks', 'assignments.task_id', '=', 'tasks.id')
                    ->where('tasks.project_id', $project->id)
                    ->where('tasks.status', 'in_progress')
                    ->groupBy('assignments.user_id')
                    ->having(DB::raw('COUNT(*)'), '>=', $ruleDef['threshold'])
                    ->pluck('assignments.user_id');
                $triggered = $overloaded->isNotEmpty();
                $evidence = ['overloaded_users' => $overloaded->toArray(), 'threshold' => $ruleDef['threshold']];
                break;

            case 'unassigned_tasks':
                $count = Task::where('project_id', $project->id)
                    ->whereNull('assignee_id')
                    ->count();
                $triggered = $count >= $ruleDef['threshold'];
                $evidence = ['unassigned_count' => $count];
                break;

            case 'stale_in_progress':
                $threshold = now()->subDays($ruleDef['threshold']);
                $count = Task::where('project_id', $project->id)
                    ->where('status', 'in_progress')
                    ->where('updated_at', '<', $threshold)
                    ->count();
                $triggered = $count >= 1;
                $evidence = ['stale_count' => $count, 'threshold_days' => $ruleDef['threshold']];
                break;
        }

        // 查找已存在的风险记录
        $existing = Risk::where('project_id', $project->id)
            ->where('rule_key', $ruleKey)
            ->first();

        if ($triggered) {
            if ($existing) {
                if ($existing->status === 'resolved') {
                    // Reopen resolved risk
                    $existing->update([
                        'status' => 'open',
                        'last_seen_at' => now(),
                        'occurrence_count' => $existing->occurrence_count + 1,
                        'evidence' => $evidence,
                    ]);

                    return ['action' => 'update', 'risk_id' => $existing->id];
                }

                $existing->update([
                    'last_seen_at' => now(),
                    'occurrence_count' => $existing->occurrence_count + 1,
                    'evidence' => $evidence,
                ]);

                return ['action' => 'update', 'risk_id' => $existing->id];
            }

            // Create new risk
            $risk = Risk::create([
                'project_id' => $project->id,
                'severity' => $ruleDef['severity'],
                'rule_key' => $ruleKey,
                'title' => $ruleDef['title'],
                'detail' => "{$ruleDef['detail']}（项目：{$project->name}）",
                'evidence' => $evidence,
                'status' => 'open',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'occurrence_count' => 1,
            ]);

            Log::info("Risk detected: {$ruleKey} for project {$project->name}", [
                'risk_id' => $risk->id,
                'severity' => $ruleDef['severity'],
            ]);

            return ['action' => 'create', 'risk_id' => $risk->id];
        }

        // Not triggered: auto-resolve if open
        if ($existing && $existing->status === 'open') {
            $existing->update([
                'status' => 'resolved',
                'closed_at' => now(),
            ]);

            return ['action' => 'resolve', 'risk_id' => $existing->id];
        }

        return ['action' => 'none'];
    }

    /**
     * 获取严重风险列表（推送给飞书用）
     */
    public function getCriticalRisks(): array
    {
        return Risk::where('status', 'open')
            ->whereIn('severity', ['critical', 'high'])
            ->where('last_seen_at', '>=', now()->subDay())
            ->with('project:id,name')
            ->get()
            ->toArray();
    }
}
