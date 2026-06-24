<?php

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Services\RiskDetector;

// ─────────────────────────────────────────────
// RiskDetector — 单规则检测（通过 scanAll 触发 detect）
// ─────────────────────────────────────────────

describe('RiskDetector rules', function () {

    beforeEach(function () {
        $this->detector = new RiskDetector;
    });

    test('overdue_tasks rule creates high-severity risk', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // Create overdue tasks (past due, not done)
        Task::factory()->count(3)->for($project)->create([
            'due_at' => now()->subDays(2),
            'status' => 'in_progress',
        ]);

        $results = $this->detector->scanAll();

        expect($results['new'])->toBeGreaterThanOrEqual(1);
        expect($results['by_rule'])->toHaveKey('overdue_tasks');

        // Verify risk record was created
        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')
            ->first();
        expect($risk)->not->toBeNull();
        expect($risk->severity)->toBe('high');
        expect($risk->status)->toBe('open');
    });

    test('no overdue tasks does not trigger overdue_tasks rule', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // All tasks are done
        Task::factory()->count(3)->done()->for($project)->create();

        $this->detector->scanAll();

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')
            ->first();
        expect($risk)->toBeNull();
    });

    test('no_recent_activity rule triggers when stale', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // Create a task updated 10 days ago
        Task::factory()->for($project)->create([
            'updated_at' => now()->subDays(10),
        ]);

        $results = $this->detector->scanAll();

        expect($results['by_rule'])->toHaveKey('no_recent_activity');

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'no_recent_activity')
            ->first();
        expect($risk)->not->toBeNull();
        expect($risk->severity)->toBe('medium');
    });

    test('recent activity does not trigger no_recent_activity', function () {
        $project = Project::factory()->create(['status' => 'active']);

        Task::factory()->for($project)->create([
            'updated_at' => now()->subHours(6),
        ]);

        $this->detector->scanAll();

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'no_recent_activity')
            ->first();
        expect($risk)->toBeNull();
    });

    test('unassigned_tasks rule triggers for null assignee', function () {
        $project = Project::factory()->create(['status' => 'active']);

        Task::factory()->count(2)->for($project)->create([
            'assignee_id' => null,
        ]);

        $results = $this->detector->scanAll();

        expect($results['by_rule'])->toHaveKey('unassigned_tasks');

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'unassigned_tasks')
            ->first();
        expect($risk)->not->toBeNull();
        expect($risk->severity)->toBe('low');
    });

    test('stale_in_progress rule detects stale tasks', function () {
        $project = Project::factory()->create(['status' => 'active']);

        Task::factory()->for($project)->create([
            'status' => 'in_progress',
            'updated_at' => now()->subDays(15),
        ]);

        $results = $this->detector->scanAll();

        expect($results['by_rule'])->toHaveKey('stale_in_progress');

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'stale_in_progress')
            ->first();
        expect($risk)->not->toBeNull();
    });

    test('existing risk is updated on re-detection (duplicate resolution)', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // First scan: create risk
        Task::factory()->count(2)->for($project)->create([
            'due_at' => now()->subDays(2),
            'status' => 'in_progress',
        ]);
        $firstResult = $this->detector->scanAll();
        expect($firstResult['new'])->toBeGreaterThanOrEqual(1);

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')
            ->first();
        $initialCount = $risk->occurrence_count;

        // Second scan: should update (not create new)
        $secondResult = $this->detector->scanAll();

        $risk->refresh();
        expect($secondResult['new'])->toBe(0);      // No new risk created
        expect($risk->occurrence_count)->toBe($initialCount + 1); // Count incremented
    });

    test('resolved risk is reopened on re-detection', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // Create risk first
        Task::factory()->count(2)->for($project)->create([
            'due_at' => now()->subDays(2),
            'status' => 'in_progress',
        ]);
        $this->detector->scanAll();

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')
            ->first();
        $risk->update(['status' => 'resolved']);

        // Re-scan — should reopen
        $result = $this->detector->scanAll();
        $risk->refresh();

        expect($result['new'])->toBe(0);
        expect($risk->status)->toBe('open');
    });

    test('risk auto-resolves when condition no longer met', function () {
        $project = Project::factory()->create(['status' => 'active']);

        // First: trigger risk
        Task::factory()->count(2)->for($project)->create([
            'due_at' => now()->subDays(2),
            'status' => 'in_progress',
        ]);
        $this->detector->scanAll();

        $risk = Risk::where('project_id', $project->id)
            ->where('rule_key', 'overdue_tasks')
            ->first();
        expect($risk->status)->toBe('open');

        // Second: fix all tasks (set status to done)
        Task::where('project_id', $project->id)->update(['status' => 'done']);

        // Third scan: should auto-resolve
        $result = $this->detector->scanAll();
        $risk->refresh();

        expect($risk->status)->toBe('resolved');
        expect($result['resolved'])->toBeGreaterThanOrEqual(1);
    });
});
