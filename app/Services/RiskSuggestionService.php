<?php

namespace App\Services;

use App\Models\Risk;
use Illuminate\Support\Facades\Log;

/**
 * 风险处理建议引擎
 *
 * 为 RiskDetector 检测到的每条风险生成结构化、可执行的修复建议。
 * 基于规则类型 + 证据数据动态生成，非模板填充。
 */
class RiskSuggestionService
{
    /**
     * 为一条风险记录生成建议
     *
     * @return array{suggestion: string, data: array}
     */
    public function generate(Risk $risk): array
    {
        $ruleKey = $risk->rule_key;
        $evidence = $risk->evidence ?? [];

        $suggestion = match ($ruleKey) {
            'overdue_tasks' => $this->suggestOverdueTasks($evidence),
            'high_overdue_ratio' => $this->suggestHighOverdueRatio($evidence),
            'no_recent_activity' => $this->suggestNoRecentActivity($evidence),
            'team_overload' => $this->suggestTeamOverload($evidence),
            'unassigned_tasks' => $this->suggestUnassignedTasks($evidence),
            'stale_in_progress' => $this->suggestStaleInProgress($evidence),
            default => $this->suggestDefault($risk),
        };

        // 保存到模型
        $risk->update([
            'suggestion' => $suggestion['suggestion'],
            'suggestion_data' => $suggestion['data'],
        ]);

        Log::info("Risk suggestion generated for risk {$risk->id}", [
            'rule_key' => $ruleKey,
            'priority' => $suggestion['data']['priority'] ?? 'normal',
        ]);

        return $suggestion;
    }

    /**
     * 为所有 open 状态且无建议的风险生成建议
     */
    public function generateAll(): array
    {
        $risks = Risk::open()
            ->whereNull('suggestion')
            ->get();

        $results = ['generated' => 0, 'skipped' => 0];
        foreach ($risks as $risk) {
            try {
                $this->generate($risk);
                $results['generated']++;
            } catch (\Throwable $e) {
                Log::warning("Failed to generate suggestion for risk {$risk->id}", [
                    'error' => $e->getMessage(),
                ]);
                $results['skipped']++;
            }
        }

        return $results;
    }

    /**
     * 应用建议（标记风险已按建议处理）
     */
    public function applySuggestion(Risk $risk, ?string $note = null): bool
    {
        if (! $risk->suggestion) {
            return false;
        }

        $data = $risk->suggestion_data ?? [];
        $data['applied_note'] = $note;
        $data['applied_at'] = now()->toIso8601String();

        $risk->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'suggestion_applied_at' => now(),
            'meta' => array_merge($risk->meta ?? [], ['suggestion_applied' => true]),
            'suggestion_data' => $data,
        ]);

        return true;
    }

    /**
     * 确认风险（仅标记已读，不应用建议）
     */
    public function acknowledge(Risk $risk): bool
    {
        return $risk->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * 忽略风险（标记并关闭）
     */
    public function dismiss(Risk $risk, ?string $reason = null): bool
    {
        $meta = $risk->meta ?? [];
        $meta['dismiss_reason'] = $reason;
        $meta['dismissed_at'] = now()->toIso8601String();

        return $risk->update([
            'status' => 'closed',
            'closed_at' => now(),
            'meta' => $meta,
        ]);
    }

    /**
     * 延期任务建议
     */
    protected function suggestOverdueTasks(array $evidence): array
    {
        $count = $evidence['overdue_count'] ?? 0;

        $steps = [];
        $steps[] = '📋 识别所有延期任务，按优先级排序';
        $steps[] = '👤 与任务负责人沟通，评估完成所需时间';
        if ($count > 3) {
            $steps[] = '🔄 考虑将部分任务重新分配给负载较轻的成员';
        }
        $steps[] = '📅 与项目经理确认截止日期是否需要正式延期';
        $steps[] = '✅ 更新任务截止日期并通知相关干系人';

        return [
            'suggestion' => "当前有 **{$count}** 个任务超过截止日期未完成。建议按以下步骤处理：\n\n"
                ."1. **优先处理** — 按严重程度从高到低排序，优先解决阻塞性任务\n"
                ."2. **逐一沟通** — 与每个任务的负责人确认当前进度和阻塞原因\n"
                .($count > 3 ? "3. **重新分配** — 超过3个任务延期时，考虑将部分任务分配给负载更轻的成员\n" : '')
                ."3. **调整计划** — 如必要，正式更新里程碑计划并通知干系人\n"
                .'4. **追踪闭环** — 每日跟踪延期任务直至全部关闭',
            'data' => [
                'priority' => $count > 5 ? 'urgent' : 'normal',
                'estimated_effort' => $count <= 3 ? '30分钟' : '1-2小时',
                'rule' => 'overdue_tasks',
                'action_steps' => $steps,
                'overdue_count' => $count,
            ],
        ];
    }

    /**
     * 高延期率建议
     */
    protected function suggestHighOverdueRatio(array $evidence): array
    {
        $ratio = ($evidence['ratio'] ?? 0) * 100;
        $total = $evidence['total_tasks'] ?? 0;
        $overdue = $evidence['overdue'] ?? 0;

        $steps = [];
        $steps[] = '📊 召开项目进度回顾会议，分析延期根本原因';
        $steps[] = '🔍 识别所有阻塞因素（技术难点/资源不足/需求变更）';
        $steps[] = '📋 制定风险缓解计划，为每项延期分配责任人';
        if ($ratio > 50) {
            $steps[] = '🚨 延期率超过 50%，建议上报管理层并申请资源支持';
        }
        $steps[] = '📈 每日追踪延期率指标，确保逐步下降';

        return [
            'suggestion' => "项目延期率为 **{$ratio}%**（{$overdue}/{$total} 任务），超过 30% 阈值。建议：\n\n"
                ."1. **根因分析** — 召集项目团队分析延期的核心原因\n"
                ."2. **阻塞清单** — 列出所有阻塞项并为每项指定负责人\n"
                ."3. **里程碑评估** — 评估是否影响关键里程碑，必要时进行调整\n"
                .($ratio > 50 ? "4. **上报请求** — 延期率超过50%，建议向上级申请额外资源\n" : '')
                .'4. **持续监控** — 每日检查延期率变化趋势',
            'data' => [
                'priority' => $ratio > 50 ? 'urgent' : 'high',
                'estimated_effort' => '2-4小时',
                'rule' => 'high_overdue_ratio',
                'action_steps' => $steps,
                'overdue_ratio' => round($ratio, 1),
                'overdue_count' => $overdue,
                'total_tasks' => $total,
            ],
        ];
    }

    /**
     * 长期无活跃建议
     */
    protected function suggestNoRecentActivity(array $evidence): array
    {
        $days = $evidence['days_since'] ?? 7;

        $steps = [];
        $steps[] = '🔍 确认项目当前状态（暂停/完成/遗忘）';
        if ($days > 14) {
            $steps[] = '⚠️ 超过14天无活动，建议与项目经理确认项目是否需要正式关闭';
        }
        $steps[] = '📝 如项目仍需推进，安排一次启动会议重新分配任务';
        $steps[] = '📊 如果项目已实质完成，更新状态为 completed 并归档';
        $steps[] = '✅ 通知团队项目最新状态';

        return [
            'suggestion' => "项目已连续 **{$days}** 天无任何任务更新。建议：\n\n"
                ."1. **状态确认** — 联系项目经理确认项目是暂停、进行中还是已被遗忘\n"
                .($days > 14 ? "2. **关闭评估** — 超过14天无活动，考虑是否应正式关闭项目\n" : '')
                ."2. **重启计划** — 如仍需推进，安排一次启动会议并重新分配最近任务\n"
                ."3. **正式更新** — 根据实际情况更新项目状态字段\n"
                .'4. **通知团队** — 确保所有成员了解项目当前状态和下一步计划',
            'data' => [
                'priority' => $days > 21 ? 'urgent' : ($days > 14 ? 'high' : 'normal'),
                'estimated_effort' => '30分钟',
                'rule' => 'no_recent_activity',
                'action_steps' => $steps,
                'inactive_days' => $days,
            ],
        ];
    }

    /**
     * 成员负载过重建议
     */
    protected function suggestTeamOverload(array $evidence): array
    {
        $users = $evidence['overloaded_users'] ?? [];
        $threshold = $evidence['threshold'] ?? 5;

        $steps = [];
        $steps[] = '👥 识别负载过重的团队成员并评估其当前任务列表';
        $steps[] = '📋 将部分非紧急任务重新分配给负载较轻的成员';
        $steps[] = '📊 检查工作负载分布是否存在不均衡';
        if (count($users) > 2) {
            $steps[] = '🆘 多名成员过载，建议申请增加临时人力支持';
        }
        $steps[] = '✅ 调整后持续监控团队负载一周';

        return [
            'suggestion' => '有 **'.count($users)."** 位成员同时进行中任务超过 {$threshold} 个，可能存在过载风险。建议：\n\n"
                ."1. **负载评估** — 查看每个过载成员的全部任务列表\n"
                ."2. **任务重分配** — 将低优先级任务分配给负载较轻的同事\n"
                ."3. **检查瓶颈** — 是否存在单点依赖，某项工作高度集中在某一人\n"
                .(count($users) > 2 ? "4. **资源请求** — 多人过载时建议申请短期支援\n" : '')
                .'4. **持续观察** — 调整后一周内持续关注团队负载变化',
            'data' => [
                'priority' => 'high',
                'estimated_effort' => '1-2小时',
                'rule' => 'team_overload',
                'action_steps' => $steps,
                'overloaded_users' => $users,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * 未分配任务建议
     */
    protected function suggestUnassignedTasks(array $evidence): array
    {
        $count = $evidence['unassigned_count'] ?? 0;

        $steps = [];
        $steps[] = '📋 列出所有未分配任务，按优先级排序';
        $steps[] = '👤 根据技能和负载为每个任务指派合适的负责人';
        $steps[] = '📅 为新分配的任务设置明确的截止日期';

        return [
            'suggestion' => "有 **{$count}** 个任务尚未分配给任何人。建议：\n\n"
                ."1. **列出清单** — 查看所有未分配任务的详情\n"
                ."2. **按技能匹配** — 根据任务类型和成员技能分配负责人\n"
                ."3. **设定截止日** — 为每个新分配的任务设置合理的截止日期\n"
                .'4. **确认接受** — 确保被指派成员知晓并接受任务',
            'data' => [
                'priority' => 'normal',
                'estimated_effort' => $count <= 5 ? '15分钟' : '30分钟',
                'rule' => 'unassigned_tasks',
                'action_steps' => $steps,
                'unassigned_count' => $count,
            ],
        ];
    }

    /**
     * 长期进行中任务建议
     */
    protected function suggestStaleInProgress(array $evidence): array
    {
        $count = $evidence['stale_count'] ?? 0;
        $days = $evidence['threshold_days'] ?? 14;

        $steps = [];
        $steps[] = '🔍 逐一检查长期未更新的任务';
        $steps[] = '📞 与任务负责人确认进度 — 是否遇到阻塞或已实际完成但未更新状态';
        $steps[] = '🚫 如任务已无实际进展，考虑关闭并归档';
        $steps[] = '📋 如有阻塞，更新阻塞原因并安排解决';

        return [
            'suggestion' => "有 **{$count}** 个任务超过 {$days} 天仍处于「进行中」状态。建议：\n\n"
                ."1. **逐项确认** — 检查每个任务的最近更新记录\n"
                ."2. **联系负责人** — 确认是遇到阻塞、被搁置还是已实际完成\n"
                ."3. **状态更新** — 已完成则标记 done，无进展则标记 blocked 或关闭\n"
                .'4. **清理积压** — 定期清理过期 in_progress 任务，保持数据准确',
            'data' => [
                'priority' => $count > 5 ? 'high' : 'normal',
                'estimated_effort' => $count <= 3 ? '15分钟' : '30分钟-1小时',
                'rule' => 'stale_in_progress',
                'action_steps' => $steps,
                'stale_count' => $count,
                'threshold_days' => $days,
            ],
        ];
    }

    /**
     * 默认建议（未知规则类型）
     */
    protected function suggestDefault(Risk $risk): array
    {
        $steps = [];
        $steps[] = '🔍 查阅风险详情，了解具体内容';
        $steps[] = '📋 根据实际情况制定处理方案';
        $steps[] = '✅ 处理完成后更新风险状态';

        return [
            'suggestion' => "建议查阅风险详情并自行评估处理方案。\n\n"
                ."1. 查看风险详情了解完整上下文\n"
                ."2. 评估影响范围和紧急程度\n"
                ."3. 制定并执行处理方案\n"
                .'4. 完成后更新风险状态为已处理',
            'data' => [
                'priority' => 'normal',
                'estimated_effort' => '视情况而定',
                'rule' => $risk->rule_key,
                'action_steps' => $steps,
            ],
        ];
    }
}
