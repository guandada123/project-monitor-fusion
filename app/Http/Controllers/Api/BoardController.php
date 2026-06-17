<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * 看板数据 — 按状态分组的任务列表
     *
     * @queryParam project_id int 按项目筛选
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->with(['project:id,name', 'assignees:id,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $tasks = $query->orderBy('sort_order')->get()->groupBy('status');

        $columns = ['todo', 'in_progress', 'review', 'done', 'blocked'];
        $board = collect($columns)->mapWithKeys(function ($status) use ($tasks) {
            return [$status => $tasks->get($status, collect())];
        });

        return ApiResponse::success([
            'columns' => $board,
            'statuses' => [
                'todo'        => '待办',
                'in_progress' => '进行中',
                'review'      => '审核中',
                'done'        => '已完成',
                'blocked'     => '已阻塞',
            ],
        ]);
    }

    /**
     * 更新看板任务（拖拽更新状态/排序）
     *
     * @urlParam task int required 任务 ID
     * @bodyParam status string target status (todo/in_progress/review/done/blocked)
     * @bodyParam sort_order int 排序值
     */
    public function updateTask(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'nullable|string|in:todo,in_progress,review,done,blocked',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $task->update($validated);
        $task->load(['project:id,name', 'assignees:id,name']);

        return ApiResponse::success($task, '看板任务已更新');
    }
}
