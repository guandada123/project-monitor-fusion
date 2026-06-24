<?php

use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\get;

test('reports page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $response = get('/reports');
    $response->assertStatus(200);
    $response->assertSeeLivewire('report-center');
});

test('reports page shows project data', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Project::factory(3)->create();

    $response = get('/reports');
    $response->assertStatus(200);
});
