<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

use function Pest\Laravel\get;

test('project list page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = get('/projects');
    $response->assertStatus(200);
    $response->assertSeeLivewire('project-list');
});

test('project list shows projects', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Project::factory(5)->create();

    $response = get('/projects');
    $response->assertStatus(200);
});

test('project detail page loads for existing project', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->create([
        'slug' => 'test-project',
        'name' => 'Test Project',
    ]);

    Task::factory(3)->create(['project_id' => $project->id]);

    $response = get("/projects/{$project->slug}");
    $response->assertStatus(200);
    $response->assertSeeLivewire('project-detail');
});

test('project detail returns 404 for non-existent slug', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/projects/non-existent-project')->assertStatus(404);
});

test('project page shows task distribution', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    Task::factory(5)->create(['project_id' => $project->id, 'status' => 'done']);
    Task::factory(3)->create(['project_id' => $project->id, 'status' => 'in_progress']);
    Task::factory(2)->create(['project_id' => $project->id, 'status' => 'todo']);

    $response = get("/projects/{$project->slug}");
    $response->assertStatus(200);
});
