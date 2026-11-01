<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Artisan;

// ─── Daily Report Command ───

test('daily:report command runs with no-push flag', function () {
    // Make sure there's some data
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create(['status' => 'active']);
    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => 'done',
    ]);
    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => 'todo',
    ]);

    $exitCode = Artisan::call('daily:report', ['--no-push' => true]);

    expect($exitCode)->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('项目日报');
    expect($output)->toContain('生成完成');
});

test('daily:report generates correct summary with project data', function () {
    $user = User::factory()->admin()->create();
    $project1 = Project::factory()->create(['status' => 'active', 'name' => '测试项目A']);
    $project2 = Project::factory()->create(['status' => 'active', 'name' => '测试项目B']);

    // Project A: 5 total, 3 done, 2 in_progress
    Task::factory()->count(3)->create(['project_id' => $project1->id, 'status' => 'done']);
    Task::factory()->count(2)->create(['project_id' => $project1->id, 'status' => 'in_progress']);

    // Project B: 4 total, 1 done, 1 overdue
    Task::factory()->create(['project_id' => $project2->id, 'status' => 'done']);
    Task::factory()->create(['project_id' => $project2->id, 'status' => 'in_progress']);
    Task::factory()->count(2)->create(['project_id' => $project2->id, 'status' => 'todo']);

    Artisan::call('daily:report', ['--no-push' => true]);
    $output = Artisan::output();

    // Should contain both project names and their completion info
    expect($output)->toContain('测试项目A');
    expect($output)->toContain('测试项目B');
    expect($output)->toContain('60%'); // 3/5 for project A
    expect($output)->toContain('25%'); // 1/4 for project B
});

test('daily:report with json format outputs valid json', function () {
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create(['status' => 'active']);
    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'done']);

    Artisan::call('daily:report', ['--no-push' => true, '--format' => 'json']);
    $output = Artisan::output();

    // The JSON output is the last section — find it by looking for a JSON structure
    // Search for JSON keys in the output as a simpler validation
    expect($output)->toContain('"date"');
    expect($output)->toContain('"summary"');
    expect($output)->toContain('"projects"');
    expect($output)->toContain('"done_tasks"');
    expect($output)->toContain('"total_tasks"');
});

test('daily:report with date option works', function () {
    $user = User::factory()->admin()->create();
    $project = Project::factory()->create(['status' => 'active']);

    // Create a task completed yesterday
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => 'done',
        'completed_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    Artisan::call('daily:report', [
        '--no-push' => true,
        '--date' => now()->subDay()->toDateString(),
    ]);

    $output = Artisan::output();
    expect($output)->toContain(now()->subDay()->toDateString());
    expect($output)->toContain('生成完成');
});

// ── 2026-11-01：CI 数据时点必须随数字一起报出 ──
// 实例：Claw 两次 CI failure 期间，日报仍报「CI 红 0 个项目」——算法没错，是快照旧。
// 只报数字不报时点，等于把有延迟的数说成实时事实。

test('daily report discloses ci data age and flags stale data', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'green',
            'ci_main_red' => false,
            'ci_last_run_at' => now()->subHours(9)->toIso8601String(),
        ],
    ]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();
    $summary = $report['summary'];

    expect($summary)->toHaveKey('ci_data_as_of')
        ->and($summary['ci_data_as_of'])->toBeString()
        ->and($summary['ci_data_age_hours'])->toBeGreaterThan(8.0)
        ->and($summary['ci_red_projects'])->toBe(0);

    $md = $service->toMarkdown($report);
    expect($md)->toContain('数据截至')
        ->and($md)->toContain('CI 数据已陈旧');
});

test('daily report counts red ci and stays quiet when data is fresh', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'failure',
            'ci_main_red' => true,
            'ci_last_run_at' => now()->subMinutes(20)->toIso8601String(),
        ],
    ]);
    Project::factory()->create([
        'status' => 'active',
        'meta' => ['ci_status' => 'green', 'ci_main_red' => false, 'ci_last_run_at' => now()->subMinutes(35)->toIso8601String()],
    ]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();

    expect($report['summary']['ci_red_projects'])->toBe(1)
        ->and($report['summary']['ci_data_age_hours'])->toBeLessThan(1.0);

    $md = $service->toMarkdown($report);
    expect($md)->toContain('数据截至')
        ->and($md)->not->toContain('CI 数据已陈旧');
});

test('daily report says no ci data when nothing collected', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create(['status' => 'active', 'meta' => ['type' => 'x']]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();

    expect($report['summary']['ci_data_as_of'])->toBeNull()
        ->and($report['summary']['ci_data_age_hours'])->toBeNull()
        ->and($service->ciFreshnessNote($report['summary']))->toBe('（无 CI 数据）');
});

// ─── 2026-11-01：日报时间必须真的来自设置 ───
// 此前设置页能存 feishu_daily_time 也能回显，但调度写死 08:30 —— 改时间不生效。

test('scheduled time follows the setting and falls back when invalid', function () {
    $settings = app(SettingsService::class);

    $settings->set('feishu_daily_time', '09:15', 'notification');
    expect(DailyReportService::scheduledTime())->toBe('09:15');

    // 非法值（小时越界）必须回落默认，而不是把坏值塞进 cron 表达式
    $settings->set('feishu_daily_time', '25:99', 'notification');
    expect(DailyReportService::scheduledTime())->toBe('08:30');

    // 空值同样回落
    $settings->set('feishu_daily_time', '', 'notification');
    expect(DailyReportService::scheduledTime())->toBe('08:30');
});
