<?php

use App\Livewire\RiskManager;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Services\RiskDetector;
use App\Services\RiskSuggestionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\get;

// ─────────────────────────────────────────────
// Risk Model
// ─────────────────────────────────────────────

test('risk belongs to a project', function () {
    $risk = Risk::factory()->create();

    expect($risk->project)->toBeInstanceOf(Project::class);
});

test('risk open scope returns only open risks', function () {
    Risk::factory()->open()->create(['rule_key' => 'overdue_tasks']);
    Risk::factory()->create(['status' => 'acknowledged']);
    Risk::factory()->create(['status' => 'closed']);

    $openRisks = Risk::open()->get();

    expect($openRisks)->toHaveCount(1);
    expect($openRisks->first()->status)->toBe('open');
});

test('risk critical scope returns only critical severity', function () {
    Risk::factory()->create(['severity' => 'critical']);
    Risk::factory()->create(['severity' => 'high']);
    Risk::factory()->create(['severity' => 'medium']);

    $critical = Risk::critical()->get();

    expect($critical)->toHaveCount(1);
});

test('risk byRule scope filters by rule_key', function () {
    Risk::factory()->create(['rule_key' => 'overdue_tasks']);
    Risk::factory()->create(['rule_key' => 'team_overload']);
    Risk::factory()->create(['rule_key' => 'low_coverage']);

    $overdue = Risk::byRule('overdue_tasks')->get();

    expect($overdue)->toHaveCount(1);
});

test('risk casts evidence and meta as array', function () {
    $risk = Risk::factory()->create([
        'evidence' => ['key' => 'value'],
        'meta' => ['source' => 'auto'],
    ]);

    expect($risk->evidence)->toBeArray();
    expect($risk->meta)->toBeArray();
    expect($risk->evidence['key'])->toBe('value');
});

test('risk casts suggestion_data as array', function () {
    $risk = Risk::factory()->create([
        'suggestion' => 'Test suggestion',
        'suggestion_data' => ['priority' => 'high', 'rule' => 'overdue_tasks'],
    ]);

    expect($risk->suggestion_data)->toBeArray();
    expect($risk->suggestion_data['priority'])->toBe('high');
});

// ─────────────────────────────────────────────
// RiskSuggestionService — Rule-based Suggestions
// ─────────────────────────────────────────────

describe('RiskSuggestionService', function () {

    test('generate creates suggestion for overdue_tasks', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 6],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        $risk->refresh();
        expect($result['suggestion'])->toContain('6');
        expect($risk->suggestion)->not->toBeNull();
        expect($risk->suggestion_data['priority'])->toBe('urgent');
        expect($risk->suggestion_data['rule'])->toBe('overdue_tasks');
    });

    test('generate creates suggestion for high_overdue_ratio', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'high_overdue_ratio',
            'evidence' => ['ratio' => 0.45, 'total_tasks' => 20, 'overdue' => 9],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('45%');
        expect($result['data']['priority'])->toBe('high');
    });

    test('generate marks ratio > 50% as urgent', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'high_overdue_ratio',
            'evidence' => ['ratio' => 0.65, 'total_tasks' => 20, 'overdue' => 13],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['data']['priority'])->toBe('urgent');
        expect($result['suggestion'])->toContain('上报');
    });

    test('generate creates suggestion for no_recent_activity', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'no_recent_activity',
            'evidence' => ['days_since' => 10],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('10');
        expect($result['data']['priority'])->toBe('normal');
    });

    test('generate marks > 14 days inactivity as high', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'no_recent_activity',
            'evidence' => ['days_since' => 18],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['data']['priority'])->toBe('high');
    });

    test('generate marks > 21 days inactivity as urgent', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'no_recent_activity',
            'evidence' => ['days_since' => 30],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['data']['priority'])->toBe('urgent');
    });

    test('generate creates suggestion for team_overload', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'team_overload',
            'evidence' => ['overloaded_users' => ['user_a', 'user_b'], 'threshold' => 5],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('2');
        expect($result['data']['priority'])->toBe('high');
    });

    test('generate creates suggestion for unassigned_tasks', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'unassigned_tasks',
            'evidence' => ['unassigned_count' => 8],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('8');
        expect($result['data']['priority'])->toBe('normal');
    });

    test('generate creates suggestion for stale_in_progress', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'stale_in_progress',
            'evidence' => ['stale_count' => 4, 'threshold_days' => 14],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('4');
        expect($result['data']['rule'])->toBe('stale_in_progress');
    });

    test('generate stale count > 5 marks as high', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'stale_in_progress',
            'evidence' => ['stale_count' => 10, 'threshold_days' => 14],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['data']['priority'])->toBe('high');
    });

    test('generate uses default suggestion for unknown rule_key', function () {
        $risk = Risk::factory()->open()->create([
            'rule_key' => 'unknown_rule',
            'evidence' => [],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->generate($risk);

        expect($result['suggestion'])->toContain('查阅风险详情');
    });

    // ─────────────────────────────────────────────
    // RiskSuggestionService — Actions
    // ─────────────────────────────────────────────

    test('applySuggestion marks risk as acknowledged', function () {
        $risk = Risk::factory()->open()->create([
            'suggestion' => 'Some suggestion text',
            'suggestion_data' => ['priority' => 'normal'],
        ]);
        $service = app(RiskSuggestionService::class);

        $result = $service->applySuggestion($risk);

        expect($result)->toBeTrue();
        $risk->refresh();
        expect($risk->status)->toBe('acknowledged');
        expect($risk->acknowledged_at)->not->toBeNull();
        expect($risk->suggestion_applied_at)->not->toBeNull();
        expect($risk->meta['suggestion_applied'])->toBeTrue();
    });

    test('applySuggestion returns false if no suggestion exists', function () {
        $risk = Risk::factory()->open()->create(['suggestion' => null]);
        $service = app(RiskSuggestionService::class);

        $result = $service->applySuggestion($risk);

        expect($result)->toBeFalse();
    });

    test('applySuggestion stores optional note', function () {
        $risk = Risk::factory()->open()->create([
            'suggestion' => 'Fix the issue',
            'suggestion_data' => ['priority' => 'normal'],
        ]);
        $service = app(RiskSuggestionService::class);

        $service->applySuggestion($risk, '已按建议处理完成');

        $risk->refresh();
        expect($risk->suggestion_data['applied_note'])->toBe('已按建议处理完成');
    });

    test('acknowledge marks risk as acknowledged without applying suggestion', function () {
        $risk = Risk::factory()->open()->create();
        $service = app(RiskSuggestionService::class);

        $result = $service->acknowledge($risk);

        expect($result)->toBeTrue();
        $risk->refresh();
        expect($risk->status)->toBe('acknowledged');
        expect($risk->acknowledged_at)->not->toBeNull();
        expect($risk->suggestion_applied_at)->toBeNull();
    });

    test('dismiss closes risk with reason', function () {
        $risk = Risk::factory()->open()->create();
        $service = app(RiskSuggestionService::class);

        $result = $service->dismiss($risk, '误报');

        expect($result)->toBeTrue();
        $risk->refresh();
        expect($risk->status)->toBe('closed');
        expect($risk->closed_at)->not->toBeNull();
        expect($risk->meta['dismiss_reason'])->toBe('误报');
    });

    test('dismiss closes risk without reason', function () {
        $risk = Risk::factory()->open()->create();
        $service = app(RiskSuggestionService::class);

        $service->dismiss($risk);

        $risk->refresh();
        expect($risk->status)->toBe('closed');
        expect($risk->meta['dismiss_reason'])->toBeNull();
    });

    test('generateAll generates suggestions for open risks without suggestion', function () {
        $service = app(RiskSuggestionService::class);
        $risk1 = Risk::factory()->open()->create([
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 3],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $risk2 = Risk::factory()->open()->create([
            'rule_key' => 'team_overload',
            'evidence' => ['overloaded_users' => ['user_a'], 'threshold' => 5],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        // This one already has a suggestion — should be skipped
        Risk::factory()->open()->create([
            'rule_key' => 'unassigned_tasks',
            'evidence' => ['unassigned_count' => 2],
            'suggestion' => 'Existing suggestion',
            'suggestion_data' => ['priority' => 'normal'],
        ]);

        $result = $service->generateAll();

        expect($result['generated'])->toBe(2);
        expect($result['skipped'])->toBe(0);
        $risk1->refresh();
        $risk2->refresh();
        expect($risk1->suggestion)->not->toBeNull();
        expect($risk2->suggestion)->not->toBeNull();
    });
});

// ─────────────────────────────────────────────
// RiskDetector
// ─────────────────────────────────────────────

describe('RiskDetector', function () {

    test('detects overdue tasks', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'due_at' => now()->subDays(2),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')->first();
        expect($risk)->not->toBeNull();
        expect($risk->evidence['overdue_count'])->toBe(3);
    });

    test('detects high overdue ratio', function () {
        $project = Project::factory()->create(['status' => 'active']);
        // 8 total tasks, 3 overdue = 37.5% ratio (over 30% threshold)
        Task::factory()->count(5)->create([
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'due_at' => now()->subDays(1),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'high_overdue_ratio')->first();
        expect($risk)->not->toBeNull();
        expect($risk->evidence['ratio'])->toBeGreaterThanOrEqual(0.3);
    });

    test('detects no recent activity', function () {
        $project = Project::factory()->create(['status' => 'active']);
        // Use DB insert directly to control exact timestamps
        DB::table('tasks')->insert([
            'project_id' => $project->id,
            'external_id' => 'ext-001',
            'title' => 'Old task for inactivity detection',
            'status' => 'done',
            'priority' => 'medium',
            'created_at' => now()->subDays(14),
            'updated_at' => now()->subDays(14),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'no_recent_activity')->first();
        expect($risk)->not->toBeNull();
        expect($risk->evidence['days_since'])->toBeGreaterThanOrEqual(7);
    });

    test('detects team overload', function () {
        $project = Project::factory()->create(['status' => 'active']);
        $user = User::factory()->create();
        // Create 5+ in_progress tasks for the same user
        $tasks = Task::factory()->count(6)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);
        foreach ($tasks as $task) {
            Assignment::factory()->assignee()->create([
                'task_id' => $task->id,
                'user_id' => $user->id,
            ]);
        }
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'team_overload')->first();
        expect($risk)->not->toBeNull();
    });

    test('detects unassigned tasks', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Task::factory()->count(4)->create([
            'project_id' => $project->id,
            'assignee_id' => null,
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'unassigned_tasks')->first();
        expect($risk)->not->toBeNull();
        expect($risk->evidence['unassigned_count'])->toBe(4);
    });

    test('detects stale in_progress tasks', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'updated_at' => now()->subDays(20),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result['new'])->toBeGreaterThanOrEqual(1);
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'stale_in_progress')->first();
        expect($risk)->not->toBeNull();
        expect($risk->evidence['stale_count'])->toBe(2);
    });

    test('detector updates existing risk rather than creating duplicate', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'due_at' => now()->subDays(1),
        ]);
        $detector = app(RiskDetector::class);

        // First scan creates
        $first = $detector->scanAll();
        expect($first['new'])->toBeGreaterThanOrEqual(1);

        // Second scan updates (doesn't create new)
        $second = $detector->scanAll();
        expect($second['new'])->toBe(0);
        expect($second['updated'])->toBeGreaterThanOrEqual(1);
    });

    test('detector auto-resolves risk when condition clears', function () {
        $project = Project::factory()->create(['status' => 'active']);
        // First create the risk directly
        $risk = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'unassigned_tasks',
            'evidence' => ['unassigned_count' => 1],
        ]);
        $detector = app(RiskDetector::class);

        // Scan — no unassigned tasks exist, so it should auto-resolve
        $result = $detector->scanAll();

        expect($result['resolved'])->toBe(1);
        $risk->refresh();
        expect($risk->status)->toBe('resolved');
    });

    test('detector skips non-active projects', function () {
        $project = Project::factory()->create(['status' => 'archived']);
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'due_at' => now()->subDays(2),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        $risk = Risk::where('project_id', $project->id)->first();
        expect($risk)->toBeNull();
    });

    test('getCriticalRisks returns recent open high/critical risks', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'severity' => 'critical',
            'last_seen_at' => now(),
        ]);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'severity' => 'high',
            'last_seen_at' => now(),
        ]);
        // Old risk should not be returned
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'severity' => 'critical',
            'last_seen_at' => now()->subDays(3),
        ]);
        $detector = app(RiskDetector::class);

        $criticalRisks = $detector->getCriticalRisks();

        expect($criticalRisks)->toHaveCount(2);
    });

    test('scanAll returns structured result', function () {
        $project = Project::factory()->create(['status' => 'active']);
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
            'due_at' => now()->subDays(1),
        ]);
        $detector = app(RiskDetector::class);

        $result = $detector->scanAll();

        expect($result)->toHaveKeys(['total_detected', 'new', 'updated', 'resolved', 'by_rule']);
        expect($result['total_detected'])->toBe($result['new'] + $result['updated']);
    });
});

// ─────────────────────────────────────────────
// RiskManager Livewire Component
// ─────────────────────────────────────────────

describe('RiskManager (Livewire)', function () {

    test('page loads with component', function () {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        get('/risks')
            ->assertStatus(200)
            ->assertSeeLivewire('risk-manager');
    });

    test('shows empty state when no risks exist', function () {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        get('/risks')
            ->assertStatus(200)
            ->assertSee('没有匹配的风险记录');
    });

    test('loads stats on mount', function () {
        $project = Project::factory()->create();
        Risk::factory()->open()->count(3)->create(['project_id' => $project->id]);
        Risk::factory()->create(['status' => 'acknowledged', 'project_id' => $project->id]);
        Risk::factory()->create(['status' => 'closed', 'project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->assertSet('stats.open', 3)
            ->assertSet('stats.acknowledged', 1)
            ->assertSet('stats.closed', 1);
    });

    test('shows risk list', function () {
        $project = Project::factory()->create(['name' => '测试项目']);
        $risks = Risk::factory()->open()->count(3)->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $response = get('/risks');
        $response->assertStatus(200);
        foreach ($risks as $risk) {
            $response->assertSee($risk->title);
        }
    });

    test('filters by status', function () {
        $project = Project::factory()->create();
        $closedRisk = Risk::factory()->create([
            'status' => 'closed',
            'project_id' => $project->id,
            'title' => '已关闭的风险',
        ]);
        Risk::factory()->open()->count(2)->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->set('statusFilter', 'closed')
            ->assertSee('已关闭的风险');
    });

    test('filters by severity', function () {
        $project = Project::factory()->create();
        $critical = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'severity' => 'critical',
            'title' => '严重风险',
        ]);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'severity' => 'low',
            'title' => '低风险',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->set('severityFilter', 'critical')
            ->assertSee('严重风险')
            ->assertDontSee('低风险');
    });

    test('filters by project', function () {
        $projectA = Project::factory()->create(['name' => '项目A']);
        $projectB = Project::factory()->create(['name' => '项目B']);
        Risk::factory()->open()->create([
            'project_id' => $projectA->id,
            'title' => 'A相关风险',
        ]);
        Risk::factory()->open()->create([
            'project_id' => $projectB->id,
            'title' => 'B相关风险',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->set('projectFilter', (string) $projectA->id)
            ->assertSee('A相关风险')
            ->assertDontSee('B相关风险');
    });

    test('filters by rule', function () {
        $project = Project::factory()->create();
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
            'title' => '延期任务风险',
        ]);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'team_overload',
            'title' => '团队过载风险',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->set('ruleFilter', 'overdue_tasks')
            ->assertSee('延期任务风险')
            ->assertDontSee('团队过载风险');
    });

    test('searches by title', function () {
        $project = Project::factory()->create();
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'title' => '服务器安全漏洞风险',
        ]);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'title' => '数据库性能问题',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->set('search', '安全')
            ->assertSee('服务器安全漏洞风险')
            ->assertDontSee('数据库性能问题');
    });

    test('acknowledge marks risk as acknowledged', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('acknowledge', $risk->id);

        $risk->refresh();
        expect($risk->status)->toBe('acknowledged');
        expect($risk->acknowledged_at)->not->toBeNull();
    });

    test('dismiss with reason closes risk', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('confirmDismiss', $risk->id)
            ->set('dismissReason', '误报，该风险已自动恢复')
            ->call('dismiss');

        $risk->refresh();
        expect($risk->status)->toBe('closed');
        expect($risk->closed_at)->not->toBeNull();
        expect($risk->meta['dismiss_reason'])->toBe('误报，该风险已自动恢复');
    });

    test('dismiss without reason still closes risk', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('confirmDismiss', $risk->id)
            ->call('dismiss');

        $risk->refresh();
        expect($risk->status)->toBe('closed');
    });

    test('cancel dismiss resets dismissing state', function () {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('confirmDismiss', 999)
            ->assertSet('dismissingId', 999)
            ->assertSet('dismissReason', '')
            ->call('cancelDismiss')
            ->assertSet('dismissingId', null)
            ->assertSet('dismissReason', '');
    });

    test('reopen returns closed risk to open status', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->create([
            'project_id' => $project->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('reopen', $risk->id);

        $risk->refresh();
        expect($risk->status)->toBe('open');
        expect($risk->closed_at)->toBeNull();
        expect($risk->acknowledged_at)->toBeNull();
    });

    test('generateSuggestion creates suggestion for single risk', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 3],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('generateSuggestion', $risk->id);

        $risk->refresh();
        expect($risk->suggestion)->not->toBeNull();
        expect($risk->suggestion_data['rule'])->toBe('overdue_tasks');
    });

    test('generateAllSuggestions processes all open risks without suggestion', function () {
        $project = Project::factory()->create();
        Risk::factory()->open()->count(3)->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 2],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $component = Livewire::test(RiskManager::class)
            ->call('generateAllSuggestions');

        // Check that all risks now have suggestions
        $openRisks = Risk::open()->get();
        expect($openRisks->every(fn ($r) => $r->suggestion !== null))->toBeTrue();
    });

    test('bulk mode toggle works', function () {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->assertSet('bulkMode', false)
            ->call('$set', 'bulkMode', true)
            ->assertSet('bulkMode', true);
    });

    test('toggle select adds and removes risk from selection', function () {
        $project = Project::factory()->create();
        $risk1 = Risk::factory()->open()->create(['project_id' => $project->id]);
        $risk2 = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('toggleSelect', $risk1->id)
            ->assertSet('selectedIds', [$risk1->id])
            ->call('toggleSelect', $risk2->id)
            ->assertSet('selectedIds', [$risk1->id, $risk2->id])
            ->call('toggleSelect', $risk1->id)
            ->assertSet('selectedIds', [$risk2->id]);
    });

    test('bulkAcknowledge confirms multiple risks', function () {
        $project = Project::factory()->create();
        $risk1 = Risk::factory()->open()->create(['project_id' => $project->id]);
        $risk2 = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('toggleSelect', $risk1->id)
            ->call('toggleSelect', $risk2->id)
            ->call('bulkAcknowledge');

        $risk1->refresh();
        $risk2->refresh();
        expect($risk1->status)->toBe('acknowledged');
        expect($risk2->status)->toBe('acknowledged');
    });

    test('bulkGenerateSuggestions generates for selected risks', function () {
        $project = Project::factory()->create();
        $risk1 = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 3],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $risk2 = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'team_overload',
            'evidence' => ['overloaded_users' => ['user_a'], 'threshold' => 5],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('toggleSelect', $risk1->id)
            ->call('toggleSelect', $risk2->id)
            ->call('bulkGenerateSuggestions');

        $risk1->refresh();
        $risk2->refresh();
        expect($risk1->suggestion)->not->toBeNull();
        expect($risk2->suggestion)->not->toBeNull();
    });

    test('bulk operations clear selection after execution', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create(['project_id' => $project->id]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('toggleSelect', $risk->id)
            ->call('bulkAcknowledge')
            ->assertSet('selectedIds', [])
            ->assertSet('bulkMode', false);
    });

    test('loadStats recalculates after risk changes', function () {
        $project = Project::factory()->create();
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->assertSet('stats.open', 0)
            ->assertSet('stats.total', 0);

        Risk::factory()->open()->count(3)->create(['project_id' => $project->id]);

        Livewire::test(RiskManager::class)
            ->assertSet('stats.open', 3)
            ->assertSet('stats.total', 3);
    });

    test('rule options populated on mount', function () {
        $project = Project::factory()->create();
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
        ]);
        Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'team_overload',
        ]);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->assertSet('ruleOptions', ['overdue_tasks', 'team_overload']);
    });

    test('applySuggestion through livewire marks risk as acknowledged', function () {
        $project = Project::factory()->create();
        $risk = Risk::factory()->open()->create([
            'project_id' => $project->id,
            'rule_key' => 'overdue_tasks',
            'evidence' => ['overdue_count' => 3],
            'suggestion' => null,
            'suggestion_data' => null,
        ]);
        // Pre-generate suggestion
        app(RiskSuggestionService::class)->generate($risk);
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(RiskManager::class)
            ->call('applySuggestion', $risk->id);

        $risk->refresh();
        expect($risk->status)->toBe('acknowledged');
        expect($risk->suggestion_applied_at)->not->toBeNull();
    });
});
