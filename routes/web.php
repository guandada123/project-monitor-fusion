<?php

use App\Http\Controllers\LogoutController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Webhook\GitHubWebhookController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Livewire\AggregateView;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\IntegrationManager;
use App\Livewire\MetricBuilder;
use App\Livewire\MetricsManager;
use App\Livewire\ProjectCompare;
use App\Livewire\ProjectDetail;
use App\Livewire\ProjectList;
use App\Livewire\ProjectReport;
use App\Livewire\ReportCenter;
use App\Livewire\ResourceView;
use App\Livewire\RiskManager;
use App\Livewire\RoleManager;
use App\Livewire\Settings;
use App\Livewire\SlowQueryMonitor;
use App\Livewire\TaskBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/welcome', fn () => view('welcome'))->name('welcome');

// Webhook (public — HMAC-signed, no CSRF)
Route::post('/webhook/github', GitHubWebhookController::class)
    ->name('webhook.github')
    ->withoutMiddleware(VerifyCsrfToken::class);

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated routes — with permission checks
Route::middleware(['auth'])->group(function () {
    // Dashboard — requires view_dashboard
    Route::get('/', Dashboard::class)
        ->middleware('permission:view_dashboard')
        ->name('dashboard');

    // Projects — requires view_projects
    Route::get('/projects', ProjectList::class)
        ->middleware('permission:view_projects')
        ->name('projects');
    Route::get('/projects/{slug}', ProjectDetail::class)
        ->middleware('permission:view_projects')
        ->name('project.detail');

    // pm-dash merged features
    Route::get('/projects/compare', ProjectCompare::class)
        ->middleware('permission:view_projects')
        ->name('projects.compare');
    Route::get('/projects/{slug}/report', ProjectReport::class)
        ->middleware('permission:view_projects')
        ->name('projects.report');
    Route::get('/projects/{slug}/report.html', [ReportController::class, 'html'])
        ->middleware('permission:view_projects')
        ->name('projects.report.html');
    Route::get('/projects/{slug}/report.pdf', [ReportController::class, 'pdf'])
        ->middleware('permission:view_projects')
        ->name('projects.report.pdf');
    Route::post('/projects/{slug}/push-feishu', [ReportController::class, 'pushToFeishu'])
        ->middleware('permission:view_projects')
        ->name('projects.push-feishu');
    Route::post('/projects/compare/push-feishu', [ReportController::class, 'pushCompareToFeishu'])
        ->middleware('permission:view_projects')
        ->name('projects.compare.push-feishu');
    Route::get('/projects/{slug}/metrics', MetricsManager::class)
        ->middleware('permission:view_projects')
        ->name('projects.metrics');

    // Aggregate View — requires view_projects
    Route::get('/aggregate', AggregateView::class)
        ->middleware('permission:view_projects')
        ->name('aggregate');

    // Board — requires view_board
    Route::get('/board', TaskBoard::class)
        ->middleware('permission:view_board')
        ->name('board');

    // Risks — requires view_risks
    Route::get('/risks', RiskManager::class)
        ->middleware('permission:view_risks')
        ->name('risks');

    // Resources — requires view_resources
    Route::get('/resources', ResourceView::class)
        ->middleware('permission:view_resources')
        ->name('resources');

    // Metrics — requires view_metrics
    Route::get('/metrics', MetricBuilder::class)
        ->middleware('permission:view_metrics')
        ->name('metrics');

    // Reports — requires view_reports
    Route::get('/reports', ReportCenter::class)
        ->middleware('permission:view_reports')
        ->name('reports');

    // Slow Query Monitor — requires view_monitoring
    Route::get('/monitoring', SlowQueryMonitor::class)
        ->middleware('permission:view_monitoring')
        ->name('monitoring');

    // Integrations — requires manage_integrations
    Route::get('/integrations', IntegrationManager::class)
        ->middleware('permission:manage_integrations')
        ->name('integrations');

    // Settings — requires manage_settings
    Route::get('/settings', Settings::class)
        ->middleware('permission:manage_settings')
        ->name('settings');

    // Role Manager — requires manage_roles (admin only)
    Route::get('/roles', RoleManager::class)
        ->middleware('permission:manage_roles')
        ->name('roles');
});

// Logout（需认证，防止 CSRF 探测）
Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

// Dusk 浏览器测试登录路由
Route::get('/dusk/login/{userId}', function (int $userId, Request $request) {
    if ($request->has('guard')) {
        Auth::guard($request->guard)->loginUsingId($userId);
    } else {
        Auth::loginUsingId($userId);
    }

    return redirect('/');
})->middleware('web')->name('dusk.login');
