<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * 项目列表
     *
     * @queryParam status string 按状态筛选 (active/archived)
     * @queryParam source string 按数据源筛选
     * @queryParam search string 按名称/描述搜索
     * @queryParam per_page int 每页条数 (默认 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->withCount(['tasks', 'risks']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $projects = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success($projects);
    }

    /**
     * 创建项目
     *
     * @bodyParam name string required 项目名称
     * @bodyParam description string 项目描述
     * @bodyParam status string 状态 (active/archived)
     * @bodyParam source string 数据源
     * @bodyParam start_at string 开始日期 (Y-m-d)
     * @bodyParam end_at string 结束日期 (Y-m-d)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:active,archived,planning',
            'source'      => 'nullable|string|max:100',
            'start_at'    => 'nullable|date',
            'end_at'      => 'nullable|date|after_or_equal:start_at',
        ]);

        $validated['owner_id'] = $request->user()->id;
        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);

        $project = Project::create($validated);
        $project->loadCount(['tasks', 'risks']);

        return ApiResponse::created($project, '项目创建成功');
    }

    /**
     * 项目详情
     *
     * @urlParam project int required 项目 ID
     */
    public function show(Project $project): JsonResponse
    {
        $project->loadCount(['tasks', 'risks', 'metrics']);

        return ApiResponse::success($project);
    }

    /**
     * 更新项目
     *
     * @urlParam project int required 项目 ID
     * @bodyParam name string 项目名称
     * @bodyParam description string 项目描述
     * @bodyParam status string 状态
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:active,archived,planning',
            'start_at'    => 'nullable|date',
            'end_at'      => 'nullable|date|after_or_equal:start_at',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }

        $project->update($validated);

        return ApiResponse::success($project, '项目更新成功');
    }

    /**
     * 删除项目
     *
     * @urlParam project int required 项目 ID
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return ApiResponse::noContent('项目已删除');
    }

    /**
     * 更新项目状态
     *
     * @urlParam project int required 项目 ID
     * @bodyParam status string required 新状态 (active/archived/planning)
     */
    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,archived,planning',
        ]);

        $project->update($validated);

        return ApiResponse::success($project, '项目状态已更新');
    }
}
