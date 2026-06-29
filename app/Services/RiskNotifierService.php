<?php

namespace App\Services;

use App\Models\Risk;
use App\Models\User;
use App\Notifications\RiskAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * 风险通知服务 — 提取自 DailyReportCommand 和 RiskScanCommand 的重复逻辑
 *
 * 使用 RiskDetector::scanAll() 返回的 new_risk_ids 进行查询，
 * 避免 5 分钟时间窗口竞争条件，同时预加载 project 关系消除 N+1。
 */
class RiskNotifierService
{
    /**
     * 通知管理员新发现的风险
     *
     * @param  array  $riskIds  scanAll() 返回的新增风险 ID 列表
     * @return array{users_count: int, risks_count: int}
     */
    public function notifyNewRisks(array $riskIds): array
    {
        if (empty($riskIds)) {
            return ['users_count' => 0, 'risks_count' => 0];
        }

        $users = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'manager']))->get();
        if ($users->isEmpty()) {
            return ['users_count' => 0, 'risks_count' => 0];
        }

        $risks = Risk::whereIn('id', $riskIds)
            ->where('status', 'open')
            ->with('project:id,name')
            ->get();

        if ($risks->isEmpty()) {
            return ['users_count' => 0, 'risks_count' => 0];
        }

        foreach ($risks as $risk) {
            Notification::send($users, new RiskAlertNotification($risk, 'created'));
        }

        return [
            'users_count' => $users->count(),
            'risks_count' => $risks->count(),
        ];
    }
}
