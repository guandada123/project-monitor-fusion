<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Risk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    /**
     * 风险列表
     *
     * @queryParam project_id int 按项目筛选
     * @queryParam severity string 按严重级筛选 (low/medium/high/critical)
     * @queryParam status string 按状态筛选
     * @queryParam per_page int 每页条数
     */
    public function index(Request $request): JsonResponse
    {
        $query = Risk::query()->with(['project:id,name']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $risks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::success($risks);
    }

    /**
     * 风险详情
     *
     * @urlParam risk int required 风险 ID
     */
    public function show(Risk $risk): JsonResponse
    {
        $risk->load(['project']);

        return ApiResponse::success($risk);
    }

    /**
     * 更新风险状态
     *
     * @urlParam risk int required 风险 ID
     *
     * @bodyParam status string required 新状态 (open/resolved/mitigated/closed)
     * @bodyParam resolution string 解决方案说明
     */
    public function updateStatus(Request $request, Risk $risk): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,resolved,mitigated,closed',
            'resolution' => 'nullable|string|max:1000',
        ]);

        $risk->update($validated);

        return ApiResponse::success($risk->load(['project:id,name']), '风险状态已更新');
    }
}
