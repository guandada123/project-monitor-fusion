<?php

namespace App\Exports;

use App\Models\Task;
use Illuminate\Support\Collection;

class TaskExport extends BaseExport
{
    public function query(array $filters = []): Collection
    {
        $query = Task::with(['project:id,name', 'assignee:id,name']);

        // Apply project filter
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // Apply status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Apply priority filter
        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        // Apply assignee filter
        if (!empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function defaultColumns(): array
    {
        return [
            'title' => '任务标题',
            'project.name' => '所属项目',
            'assignee.name' => '负责人',
            'status' => '状态',
            'priority' => '优先级',
            'due_at' => '截止日期',
            'created_at' => '创建时间',
        ];
    }

    public function availableColumns(): array
    {
        return [
            'title' => '任务标题',
            'description' => '任务描述',
            'project.name' => '所属项目',
            'assignee.name' => '负责人',
            'status' => '状态',
            'priority' => '优先级',
            'estimate' => '预估工时(h)',
            'spent' => '实际工时(h)',
            'start_at' => '开始日期',
            'due_at' => '截止日期',
            'completed_at' => '完成时间',
            'is_overdue' => '是否逾期',
            'tags' => '标签',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }
}
