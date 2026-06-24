<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * 任务列表
     *
     * @queryParam project_id int 按项目筛选
     * @queryParam status string 按状态筛选
     * @queryParam priority string 按优先级筛选
     * @queryParam per_page int 每页条数
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->with(['project:id,name', 'assignees:id,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success($tasks);
    }

    /**
     * 创建任务
     *
     * @bodyParam project_id int required 所属项目 ID
     * @bodyParam title string required 任务标题
     * @bodyParam description string 任务描述
     * @bodyParam status string 状态
     * @bodyParam priority string 优先级 (low/medium/high/critical)
     * @bodyParam estimate float 预估工时
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:todo,in_progress,done,review,blocked',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'estimate' => 'nullable|numeric|min:0|max:999',
        ]);

        $validated['creator_id'] = $request->user()->id;
        $task = Task::create($validated);
        $task->load(['project:id,name', 'assignees:id,name']);

        return ApiResponse::created($task, '任务创建成功');
    }

    /**
     * 任务详情
     *
     * @urlParam task int required 任务 ID
     */
    public function show(Task $task): JsonResponse
    {
        $task->load(['project', 'assignees', 'creator']);

        return ApiResponse::success($task);
    }

    /**
     * 更新任务
     *
     * @urlParam task int required 任务 ID
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:todo,in_progress,done,review,blocked',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'estimate' => 'nullable|numeric|min:0|max:999',
            'spent' => 'nullable|numeric|min:0|max:999',
        ]);

        $task->update($validated);
        $task->load(['project:id,name', 'assignees:id,name']);

        return ApiResponse::success($task, '任务更新成功');
    }

    /**
     * 删除任务
     *
     * @urlParam task int required 任务 ID
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return ApiResponse::noContent('任务已删除');
    }

    /**
     * 批量更新任务状态
     *
     * @bodyParam ids array required 任务 ID 数组
     * @bodyParam status string required 目标状态
     */
    public function batchUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'integer|exists:tasks,id',
            'status' => 'required|string|in:todo,in_progress,done,review,blocked',
        ]);

        $count = Task::whereIn('id', $validated['ids'])->update([
            'status' => $validated['status'],
        ]);

        return ApiResponse::success(['updated' => $count], "{$count} 个任务状态已更新");
    }

    /**
     * 重排序任务
     *
     * @bodyParam tasks array required 任务排序数组 [{id, sort_order}]
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tasks' => 'required|array|min:1|max:50',
            'tasks.*.id' => 'required|integer|exists:tasks,id',
            'tasks.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['tasks'] as $item) {
            Task::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success(null, '任务排序已更新');
    }
}
