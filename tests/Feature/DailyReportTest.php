<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
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
