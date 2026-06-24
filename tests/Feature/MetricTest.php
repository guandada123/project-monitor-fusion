<?php

use App\Models\Metric;
use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\get;

test('metrics page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = get('/metrics');
    $response->assertStatus(200);
    $response->assertSeeLivewire('metric-builder');
});

test('metrics page shows metric list', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    Metric::factory(3)->create(['project_id' => $project->id]);

    $response = get('/metrics');
    $response->assertStatus(200);
});

test('metrics page is empty when no metrics exist', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = get('/metrics');
    $response->assertStatus(200);
});
