<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AggregateController extends Controller
{
    /**
     * 跨项目聚合统计
     *
     * Returns aggregated KPI data across all (or filtered) projects.
     *
     * @queryParam project_ids string 逗号分隔的项目 ID 列表
     */
    public function index(Request $request): JsonResponse
    {
        $projectQuery = Project::query();

        if ($request->filled('project_ids')) {
            $ids = explode(',', $request->project_ids);
            $projectQuery->whereIn('id', array_map('intval', $ids));
        }

        $projects = $projectQuery->withCount(['tasks', 'risks'])->get();
        $projectIds = $projects->pluck('id');

        if ($projectIds->isEmpty()) {
            return ApiResponse::success([
                'projects' => ['total' => 0, 'list' => []],
                'tasks' => ['total' => 0, 'done' => 0, 'in_progress' => 0, 'todo' => 0, 'blocked' => 0, 'completion_rate' => 0],
                'risks' => ['total' => 0, 'critical' => 0, 'high' => 0, 'open' => 0],
            ]);
        }

        // Task stats
        $taskStats = DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_count,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_count
            ")
            ->first();

        // Risk stats
        $riskStats = DB::table('risks')
            ->whereIn('project_id', $projectIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count
            ")
            ->first();

        $totalTasks = (int) ($taskStats->total ?? 0);
        $doneTasks = (int) ($taskStats->done_count ?? 0);
        $completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0;

        return ApiResponse::success([
            'projects' => [
                'total' => $projects->count(),
                'list' => $projects->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'status' => $p->status,
                    'source' => $p->source,
                    'task_count' => $p->tasks_count,
                    'risk_count' => $p->risks_count,
                ]),
            ],
            'tasks' => [
                'total' => $totalTasks,
                'done' => $doneTasks,
                'in_progress' => (int) ($taskStats->in_progress_count ?? 0),
                'todo' => (int) ($taskStats->todo_count ?? 0),
                'blocked' => (int) ($taskStats->blocked_count ?? 0),
                'completion_rate' => $completionRate,
            ],
            'risks' => [
                'total' => (int) ($riskStats->total ?? 0),
                'critical' => (int) ($riskStats->critical_count ?? 0),
                'high' => (int) ($riskStats->high_count ?? 0),
                'open' => (int) ($riskStats->open_count ?? 0),
            ],
        ]);
    }
}
