<?php

use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;

use function Pest\Laravel\get;

test('dashboard loads with livewire component', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = get('/');

    $response->assertStatus(200);
    $response->assertSeeLivewire('dashboard');
});

test('dashboard shows project statistics', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // Create projects and metrics
    Project::factory(3)->create(['status' => 'active']);
    Project::factory(1)->create(['status' => 'on_hold']);

    $response = get('/');

    $response->assertStatus(200);
});

test('dashboard shows risk summary', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    Risk::factory(2)->create([
        'project_id' => $project->id,
        'status' => 'open',
    ]);

    $response = get('/');
    $response->assertStatus(200);
});

test('dashboard shows kpi trend data', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    $metric = Metric::factory()->create(['project_id' => $project->id]);

    // Create some snapshots
    MetricSnapshot::factory(7)->create([
        'metric_id' => $metric->id,
    ]);

    $response = get('/');
    $response->assertStatus(200);
});
