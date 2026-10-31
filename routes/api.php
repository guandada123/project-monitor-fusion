<?php

use App\Http\Controllers\Api\AggregateController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\MetricController;
use App\Http\Controllers\Api\OpsApiController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RiskController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rate limiting: 60/min for unauthenticated, 300/min for authenticated.
| All responses follow unified JSON format:
| { success: bool, message: string, data?: mixed, errors?: array }
|
*/

// ─── Health check (public) ───
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'app' => config('app.name'),
            'version' => '1.0',
            'time' => now()->toIso8601String(),
        ],
    ]);
});

// ─── Ops Center 监控面板 (公开, 原 ops-center Flask 迁移) ───
// ⚠️ 2026-10-31 审计补充：本组原本无任何 middleware，而它暴露 /cost（成本数据）、/state、
// /automations、/roadmap 等内部运维数据，是同仓唯一没有中间件的 API 组。
// 因其可能供本机反代直连（原 ops-center Flask 迁移而来），**暂不擅加 auth**（会断现有调用，
// 需业务确认），先补限流，避免被当作无鉴权的数据源持续刷取。
// 【待确认】若确认无外部直连需求，应改为 auth:sanctum + permission:view_metrics。
Route::prefix('ops')->middleware('throttle:120,1')->group(function () {
    Route::get('/overview', [OpsApiController::class, 'overview']);
    Route::get('/automations', [OpsApiController::class, 'automations']);
    Route::get('/cost', [OpsApiController::class, 'cost']);
    Route::get('/roadmap', [OpsApiController::class, 'roadmap']);
    Route::get('/health', [OpsApiController::class, 'health']);
    Route::get('/state', [OpsApiController::class, 'state']);
});

// ─── Token management (public: create token) ───
Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:20,1');

// ─── Authenticated routes ───
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {

    // Token management — requires manage_settings
    Route::get('/tokens', [TokenController::class, 'index'])->middleware('permission:manage_settings');
    Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy'])->middleware('permission:manage_settings');

    // Projects — view_projects for reads, manage_projects for writes
    Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:view_projects');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:manage_projects');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('permission:view_projects');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('permission:manage_projects');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:manage_projects');
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->middleware('permission:manage_projects');

    // Tasks — view_board for reads, manage_projects for writes (tasks are project work items)
    Route::get('/tasks', [TaskController::class, 'index'])->middleware('permission:view_board');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('permission:manage_projects');
    // 静态路径必须先于 /tasks/{task} 声明，否则会被参数路由劫持
    // （2026-10-24：/tasks/reorder 曾被 PUT /tasks/{task} 捕获 —— pgsql 报
    //  invalid bigint "reorder"，sqlite 静默 404，接口两驱动下均不可用）
    Route::patch('/tasks/batch-status', [TaskController::class, 'batchUpdateStatus'])->middleware('permission:manage_projects');
    Route::put('/tasks/reorder', [TaskController::class, 'reorder'])->middleware('permission:manage_projects');
    // {task} 限定数字，防止任何静态段被误捕（纵深防御）
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->where('task', '[0-9]+')->middleware('permission:view_board');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->where('task', '[0-9]+')->middleware('permission:manage_projects');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->where('task', '[0-9]+')->middleware('permission:manage_projects');

    // Risks — view_risks for reads, manage_risks for writes
    Route::get('/risks', [RiskController::class, 'index'])->middleware('permission:view_risks');
    Route::get('/risks/{risk}', [RiskController::class, 'show'])->middleware('permission:view_risks');
    Route::patch('/risks/{risk}/status', [RiskController::class, 'updateStatus'])->middleware('permission:manage_risks');

    // Metrics — all require view_metrics
    Route::get('/metrics', [MetricController::class, 'index'])->middleware('permission:view_metrics');
    Route::get('/metrics/snapshots', [MetricController::class, 'snapshots'])->middleware('permission:view_metrics');
    Route::get('/metrics/aggregate', [MetricController::class, 'aggregate'])->middleware('permission:view_metrics');

    // Board — view_board for read, manage_projects for task updates
    Route::get('/board', [BoardController::class, 'index'])->middleware('permission:view_board');
    Route::put('/board/tasks/{task}', [BoardController::class, 'updateTask'])->middleware('permission:manage_projects');

    // Aggregate — requires view_projects
    Route::get('/aggregate', [AggregateController::class, 'index'])->middleware('permission:view_projects');
});
