<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    /**
     * 指标列表
     *
     * @queryParam project_id int 按项目筛选
     * @queryParam per_page int 每页条数
     */
    public function index(Request $request): JsonResponse
    {
        $query = Metric::query()->with(['project:id,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $metrics = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success($metrics);
    }

    /**
     * 指标快照历史
     *
     * @queryParam metric_id int required 指标 ID
     * @queryParam date_from string 开始日期 (Y-m-d)
     * @queryParam date_to string 结束日期 (Y-m-d)
     * @queryParam per_page int 每页条数
     */
    public function snapshots(Request $request): JsonResponse
    {
        $request->validate([
            'metric_id' => 'required|integer|exists:metrics,id',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = MetricSnapshot::where('metric_id', $request->metric_id);

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        $perPage = min((int) $request->input('per_page', 100), 500);
        $snapshots = $query->orderBy('created_at', 'asc')->paginate($perPage);

        return ApiResponse::success($snapshots);
    }

    /**
     * 指标聚合统计
     *
     * @queryParam project_id int 按项目筛选
     */
    public function aggregate(Request $request): JsonResponse
    {
        $query = Metric::query();

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $metrics = $query->with('latestSnapshot')->get();

        /** @var \Illuminate\Support\Collection<int, array> $aggregated */
        $aggregated = $metrics->map(function ($metric) {
            /** @var \App\Models\Metric $metric */
            $latest = $metric->getRelation('latestSnapshot');

            return [
                'id'          => $metric->id,
                'name'        => $metric->name,
                'project_id'  => $metric->project_id,
                'latest_value' => $latest?->value,
                'latest_recorded_at' => $latest?->created_at,
            ];
        });

        return ApiResponse::success($aggregated);
    }
}
