<?php

namespace App\Exports;

use App\Models\Risk;
use Illuminate\Support\Collection;

class RiskExport extends BaseExport
{
    public function query(array $filters = []): Collection
    {
        $query = Risk::with(['project:id,name']);

        // Apply status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Apply severity filter
        if (! empty($filters['severity']) && $filters['severity'] !== 'all') {
            $query->where('severity', $filters['severity']);
        }

        // Apply project filter
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // Apply rule filter
        if (! empty($filters['rule_key']) && $filters['rule_key'] !== 'all') {
            $query->where('rule_key', $filters['rule_key']);
        }

        // Apply search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('last_seen_at', 'desc')->get();
    }

    public function defaultColumns(): array
    {
        return [
            'title' => '风险标题',
            'project.name' => '所属项目',
            'severity' => '严重程度',
            'status' => '状态',
            'rule_key' => '规则类型',
            'occurrence_count' => '出现次数',
            'last_seen_at' => '最后发现',
        ];
    }

    public function availableColumns(): array
    {
        return [
            'title' => '风险标题',
            'detail' => '风险详情',
            'project.name' => '所属项目',
            'severity' => '严重程度',
            'status' => '状态',
            'rule_key' => '规则类型',
            'occurrence_count' => '出现次数',
            'first_seen_at' => '首次发现',
            'last_seen_at' => '最后发现',
            'acknowledged_at' => '确认时间',
            'closed_at' => '关闭时间',
            'suggestion' => '建议措施',
            'created_at' => '创建时间',
        ];
    }
}
