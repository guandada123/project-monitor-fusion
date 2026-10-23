<?php

use App\Livewire\ReportCenter;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use Livewire\Livewire;

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

test('report risk summary uses the real domain values', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $project = Project::factory()->active()->create();

    // 活跃风险口径 = open + acknowledged（历史上误用不存在的 'active'，导致恒为 0）
    Risk::factory()->open()->create(['project_id' => $project->id, 'severity' => 'high']);
    Risk::factory()->open()->create(['project_id' => $project->id, 'severity' => 'critical']);
    Risk::factory()->acknowledged()->create(['project_id' => $project->id, 'severity' => 'medium']);
    // closed 不计入活跃；low 不计入严重（历史上误用不存在的 'severe'）
    Risk::factory()->create([
        'project_id' => $project->id,
        'status' => 'closed',
        'closed_at' => now()->subDay(),
        'severity' => 'low',
    ]);

    Livewire::test(ReportCenter::class)
        ->call('generate')
        ->assertSet('reportData.summary.active_risks', 3)
        ->assertSet('reportData.summary.severe_risks', 2);
});
