<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\FeishuService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

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
// 2026-11-05 起，时点指「采集时点」(config.snapshot_generated_at)，不是 CI 最后运行时间。

test('daily report discloses ci data age and flags stale data', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'green',
            'ci_main_red' => false,
            'ci_last_run_at' => now()->subMinutes(5)->toIso8601String(),
        ],
        'config' => ['snapshot_generated_at' => now()->subHours(9)->toIso8601String()],
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
        'config' => ['snapshot_generated_at' => now()->subMinutes(20)->toIso8601String()],
    ]);
    Project::factory()->create([
        'status' => 'active',
        'meta' => ['ci_status' => 'green', 'ci_main_red' => false, 'ci_last_run_at' => now()->subMinutes(35)->toIso8601String()],
        'config' => ['snapshot_generated_at' => now()->subMinutes(35)->toIso8601String()],
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
    // factory 不带 config.snapshot_generated_at = 从未被采集器导入过 → 无数据时点
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

// 阈值钉死：采集器约 1 小时一刷，2h 没刷就该标黄。
// 这条用例的作用是「改回去会红」——把 2h 这个判断固化下来，
// 而不是让下一个人随手把阈值调回 6h 而没人发现。
test('flags ci data older than two hours as stale', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'green',
            'ci_main_red' => false,
            'ci_last_run_at' => now()->subMinutes(5)->toIso8601String(),
        ],
        'config' => ['snapshot_generated_at' => now()->subHours(3)->toIso8601String()],
    ]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();

    expect($report['summary']['ci_data_age_hours'])->toBeGreaterThan(2.0);
    expect($service->toMarkdown($report))->toContain('CI 数据已陈旧');
});

// ─── 2026-11-05：新鲜度判据回归（日报连标 3 天假陈旧）───
// 线上实况：采集器 07:20 刚刷、ops:import-snapshot 每 30 分钟导入，数据很新；
// 但旧实现拿 meta.ci_last_run_at（CI 最后一次 push 触发时间，每天 03:00）当新鲜度，
// 2h 阈值下 08:30 日报天天写「CI 数据已陈旧」。CI 没跑 ≠ 数据没采。

test('does not flag stale data when snapshot is fresh but ci has not run', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'green',
            'ci_main_red' => false,
            'ci_last_run_at' => now()->subDays(3)->toIso8601String(),
        ],
        'config' => ['snapshot_generated_at' => now()->subMinutes(10)->toIso8601String()],
    ]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();

    expect($report['summary']['ci_data_age_hours'])->toBeLessThan(1.0);
    expect($service->toMarkdown($report))->not->toContain('CI 数据已陈旧');
});

test('flags stale snapshot even when ci ran a moment ago', function () {
    $user = User::factory()->admin()->create();
    Project::factory()->create([
        'status' => 'active',
        'meta' => [
            'ci_status' => 'green',
            'ci_main_red' => false,
            'ci_last_run_at' => now()->subMinutes(2)->toIso8601String(),
        ],
        'config' => ['snapshot_generated_at' => now()->subHours(5)->toIso8601String()],
    ]);

    $service = new DailyReportService(now()->toDateString());
    $report = $service->generate();

    expect($report['summary']['ci_data_age_hours'])->toBeGreaterThan(4.0);
    expect($service->toMarkdown($report))->toContain('CI 数据已陈旧');
});

// ─── 2026-11-05 08:30 真实事故：飞书限流被误报成「未配置」，且失败仍退出 0 ───
// 自动核对任务在日志里抓到：
//   ❌ 飞书推送失败：frequency limited psm[lark.oapi.app_platform_runtime]appID[1500]
//      提示：请在系统设置中配置飞书 Webhook URL   ← 误导：webhook 明明已配好
//   ✅ 日报生成完成                              ← 假绿灯：调度器永远看不到失败
// 下面三条把「说真话」和「失败可见」都钉死。

function fakeFeishuWebhook(): void
{
    // FeishuService 会缓存 settings，必须先丢弃实例再写配置
    app()->forgetInstance(FeishuService::class);
    app(SettingsService::class)->set('feishu_webhook_url', 'https://open.feishu.cn/hook/test', 'notification');
}

test('daily:report reports rate limit honestly and exits non-zero', function () {
    fakeFeishuWebhook();
    Http::fake([
        '*' => Http::response(['code' => 9499, 'msg' => 'frequency limited psm[lark.oapi.app_platform_runtime]appID[1500]'], 200),
    ]);

    $exit = Artisan::call('daily:report');
    $out = Artisan::output();

    expect($exit)->toBe(1)
        ->and($out)->toContain('飞书应用侧限流')
        ->and($out)->toContain('daily:report --date=')
        ->and($out)->not->toContain('请配置飞书 Webhook URL')
        ->and($out)->not->toContain('✅ 日报生成完成');
});

test('daily:report gives delivery hint for non-ratelimit failures', function () {
    fakeFeishuWebhook();
    Http::fake(['*' => Http::response(['code' => 19001, 'msg' => 'internal error'], 200)]);

    $exit = Artisan::call('daily:report');
    $out = Artisan::output();

    expect($exit)->toBe(1)
        ->and($out)->toContain('请求未能送达')
        ->and($out)->not->toContain('请配置飞书 Webhook URL');
});

test('daily:report still exits zero when push succeeds', function () {
    fakeFeishuWebhook();
    Http::fake(['*' => Http::response(['code' => 0], 200)]);

    $exit = Artisan::call('daily:report');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('✅ 飞书推送成功');
});
