<?php

use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\RiskController;
use App\Http\Controllers\Api\MetricController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\AggregateController;
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

// ─── Token management (public: create token) ───
Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:20,1');

// ─── Authenticated routes ───
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {

    // Token management
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy']);

    // Projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus']);

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/batch-status', [TaskController::class, 'batchUpdateStatus']);
    Route::put('/tasks/reorder', [TaskController::class, 'reorder']);

    // Risks
    Route::get('/risks', [RiskController::class, 'index']);
    Route::get('/risks/{risk}', [RiskController::class, 'show']);
    Route::patch('/risks/{risk}/status', [RiskController::class, 'updateStatus']);

    // Metrics
    Route::get('/metrics', [MetricController::class, 'index']);
    Route::get('/metrics/snapshots', [MetricController::class, 'snapshots']);
    Route::get('/metrics/aggregate', [MetricController::class, 'aggregate']);

    // Board
    Route::get('/board', [BoardController::class, 'index']);
    Route::put('/board/tasks/{task}', [BoardController::class, 'updateTask']);

    // Aggregate
    Route::get('/aggregate', [AggregateController::class, 'index']);
});
