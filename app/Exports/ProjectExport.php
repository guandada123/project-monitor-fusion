<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Support\Collection;

class ProjectExport extends BaseExport
{
    public function query(array $filters = []): Collection
    {
        $query = Project::with(['owner', 'tasks'])
            ->withCount(['tasks as task_count', 'risks as risk_count']);

        // Apply status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Apply source filter
        if (! empty($filters['source']) && $filters['source'] !== 'all') {
            $query->where('source', $filters['source']);
        }

        // Apply search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function defaultColumns(): array
    {
        return [
            'name' => '项目名称',
            'slug' => '标识符',
            'status' => '状态',
            'source' => '数据源',
            'task_count' => '任务数',
            'risk_count' => '风险数',
            'created_at' => '创建时间',
        ];
    }

    public function availableColumns(): array
    {
        return [
            'name' => '项目名称',
            'slug' => '标识符',
            'status' => '状态',
            'source' => '数据源',
            'description' => '项目描述',
            'owner.name' => '负责人',
            'task_count' => '任务数',
            'risk_count' => '风险数',
            'start_at' => '开始日期',
            'end_at' => '结束日期',
            'completion_rate' => '完成率',
            'overdue_rate' => '逾期率',
            'open_risk_count' => '开放风险数',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }
}
