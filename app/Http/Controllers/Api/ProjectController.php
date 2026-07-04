<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $user = $request->user();
        $query = Project::query()->withCount(['tasks', 'risks']);

        // Scope to projects the user can access
        $query->whereIn('id', $user->accessibleProjectIds());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('description', "%{$search}%");
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,archived,planning',
            'source' => 'nullable|string|max:100',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        $validated['owner_id'] = $request->user()->id;
        $validated['slug'] = $this->resolveSlug($validated['name']);

        try {
            $project = Project::create($validated);
        } catch (QueryException $e) {
            // Unique constraint violation on slug — retry with incremented suffix
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'slug')) {
                $validated['slug'] = $this->resolveSlug($validated['name'], retry: true);
                $project = Project::create($validated);
            } else {
                throw $e;
            }
        }
        $project->loadCount(['tasks', 'risks']);

        return ApiResponse::created($project, '项目创建成功');
    }

    /**
     * 项目详情
     *
     * @urlParam project int required 项目 ID
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()->canAccessProject($project), 403, '无权访问此项目');

        $project->loadCount(['tasks', 'risks', 'metrics']);

        return ApiResponse::success($project);
    }

    /**
     * 更新项目
     *
     * @urlParam project int required 项目 ID
     *
     * @bodyParam name string 项目名称
     * @bodyParam description string 项目描述
     * @bodyParam status string 状态
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,archived,planning',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = $this->resolveSlug($validated['name'], $project->id);
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
        // Check for related records before soft-deleting
        $relationCounts = [
            '任务' => $project->tasks()->count(),
            '风险' => $project->risks()->count(),
            '指标' => $project->metrics()->count(),
        ];

        $nonEmpty = array_filter($relationCounts);
        if ($nonEmpty) {
            $details = implode('、', array_map(
                fn (string $label, int $count) => "{$label} {$count}条",
                array_keys($nonEmpty),
                $nonEmpty
            ));

            return ApiResponse::error(
                "项目存在关联数据，无法直接删除：{$details}。请先处理关联数据后再删除。",
                409,
            );
        }

        $project->delete();

        return ApiResponse::noContent('项目已删除');
    }

    /**
     * 更新项目状态
     *
     * @urlParam project int required 项目 ID
     *
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

    /**
     * Generate a unique slug for the given name.
     *
     * Excludes the given project ID (if any) from conflict check so a project
     * can update its own name without triggering a false positive.
     *
     * When $retry is true (invoked from catch block after a unique constraint violation),
     * skips the existence check and directly appends a suffix — the first attempt already
     * exhausted the base + incrementing sequence, so we start from a known-collision point.
     */
    private function resolveSlug(string $name, ?int $excludeId = null, bool $retry = false): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        if ($retry) {
            // Called after a unique constraint violation — just append a suffix
            return $originalSlug.'-'.time();
        }

        $query = Project::where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.($counter++);
            $query = Project::where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
