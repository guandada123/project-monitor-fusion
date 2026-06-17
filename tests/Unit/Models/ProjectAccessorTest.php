<?php

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// ─────────────────────────────────────────────
// Project Model Accessor — 预加载保护测试
// ─────────────────────────────────────────────

describe('Project completion_rate accessor', function () {

    test('completion_rate uses loaded relation without extra SQL', function () {
        $project = Project::factory()
            ->has(Task::factory()->count(4)->state(['status' => 'in_progress']))
            ->has(Task::factory()->done()->count(2))
            ->create();

        // Preload the tasks relation
        $project->load('tasks');

        DB::enableQueryLog();
        $rate = $project->completion_rate;
        $queries = DB::getQueryLog();

        // 2 done / 6 total ≈ 33.3%
        expect($rate)->toBeGreaterThan(0);
        expect($queries)->toBeEmpty(); // ✅ relation loaded, no extra SQL
    });

    test('completion_rate returns 0 when no tasks exist', function () {
        $project = Project::factory()->create();

        $project->load('tasks');

        DB::enableQueryLog();
        $rate = $project->completion_rate;

        expect($rate)->toBe(0.0);
        $queries = DB::getQueryLog();
        expect($queries)->toBeEmpty();
    });

    test('completion_rate falls back to DB query without preload', function () {
        $project = Project::factory()
            ->has(Task::factory()->count(3)->state(['status' => 'todo']))
            ->has(Task::factory()->done()->count(3))
            ->create();

        DB::enableQueryLog();
        $rate = $project->completion_rate;
        $queries = DB::getQueryLog();

        // 3 done / 6 total = 50%
        expect($rate)->toBe(50.0);
        expect($queries)->not->toBeEmpty(); // ✅ falls back to DB query
    });
});

describe('Project overdue_rate accessor', function () {

    test('overdue_rate uses loaded relation without extra SQL', function () {
        $project = Project::factory()
            ->has(Task::factory()->count(3)->state([
                'due_at' => now()->subDays(1),
                'status' => 'in_progress',
            ]))
            ->has(Task::factory()->done()->count(3))
            ->create();

        // Re-read since has() creates tasks
        $project = Project::with('tasks')->find($project->id);

        DB::enableQueryLog();
        $rate = $project->overdue_rate;
        $queries = DB::getQueryLog();

        // 3 overdue out of 3 not-done tasks = 100%
        // But we have 3 done tasks which are not counted for overdue
        expect($rate)->toBeGreaterThan(0);
        expect($queries)->toBeEmpty();
    });
});

describe('Project open_risk_count accessor', function () {

    test('open_risk_count uses loaded relation without extra SQL', function () {
        $project = Project::factory()
            ->has(\App\Models\Risk::factory()->open()->count(3))
            ->has(\App\Models\Risk::factory()->count(2)->state(['status' => 'acknowledged']))
            ->create();

        $project->load('risks');

        DB::enableQueryLog();
        $count = $project->open_risk_count;
        $queries = DB::getQueryLog();

        expect($count)->toBe(3);
        expect($queries)->toBeEmpty();
    });

    test('open_risk_count falls back to DB query without preload', function () {
        $project = Project::factory()
            ->has(\App\Models\Risk::factory()->open()->count(2))
            ->create();

        DB::enableQueryLog();
        $count = $project->open_risk_count;

        expect($count)->toBe(2);
        $queries = DB::getQueryLog();
        expect($queries)->not->toBeEmpty();
    });
});
