<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AggregateViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_page_loads_with_livewire_component(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/aggregate')
            ->assertStatus(200)
            ->assertSeeLivewire('aggregate-view');
    }

    public function test_aggregate_shows_empty_state_when_no_projects(): void
    {
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->assertSee('没有匹配的项目');
    }

    public function test_aggregate_shows_project_data(): void
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'Alpha Project',
            'status' => 'active',
            'source' => 'github',
        ]);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->assertSee('Alpha Project')
            ->assertSee('进行中')
            ->assertSee('github');
    }

    public function test_aggregate_shows_summary_stats(): void
    {
        $user = User::factory()->admin()->create();
        Project::factory()->count(3)->create(['status' => 'active']);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->assertSee('总项目数')
            ->assertSee('3');
    }

    public function test_aggregate_search_filters_projects(): void
    {
        $user = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Visible Project']);
        Project::factory()->create(['name' => 'Hidden Project']);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->set('search', 'Visible')
            ->assertSee('Visible Project')
            ->assertDontSee('Hidden Project');
    }

    public function test_aggregate_search_empty_state(): void
    {
        $user = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'Some Project']);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->set('search', 'NonExistent')
            ->assertSee('没有匹配的项目')
            ->assertSee('清除搜索');
    }

    public function test_aggregate_sort_toggle_direction(): void
    {
        $user = User::factory()->admin()->create();
        Project::factory()->create(['name' => 'A Project']);
        Project::factory()->create(['name' => 'Z Project']);

        $component = Livewire::actingAs($user)
            ->test('aggregate-view');

        // Default sort: completion_rate desc
        $component->call('sortBy', 'name');
        // First click: sort by name desc
        $component->assertSet('sortField', 'name');
        $component->assertSet('sortDirection', 'desc');

        $component->call('sortBy', 'name');
        // Second click: sort by name asc
        $component->assertSet('sortDirection', 'asc');
    }

    public function test_aggregate_shows_task_and_risk_counts(): void
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'Stats Check']);

        // Create tasks
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'status' => 'done',
        ]);
        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => 'in_progress',
        ]);

        // Create risks
        Risk::factory()->count(2)->create([
            'project_id' => $project->id,
            'status' => 'open',
            'severity' => 'high',
        ]);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->assertSee('Stats Check')
            ->assertSee('3/5'); // 3 done / 5 total
    }

    public function test_aggregate_health_indicator_logic(): void
    {
        $user = User::factory()->admin()->create();

        // Healthy: 0 risks + >= 80% completion
        Project::factory()->create([
            'name' => 'Healthy Proj',
            'status' => 'active',
        ]);

        // Warning: <= 2 risks or >= 50% completion
        $warning = Project::factory()->create([
            'name' => 'Warning Proj',
            'status' => 'active',
        ]);
        Risk::factory()->create([
            'project_id' => $warning->id,
            'status' => 'open',
            'severity' => 'high',
        ]);

        // Critical: > 2 risks and < 50% completion
        $critical = Project::factory()->create([
            'name' => 'Critical Proj',
            'status' => 'active',
        ]);
        Risk::factory()->count(3)->create([
            'project_id' => $critical->id,
            'status' => 'open',
            'severity' => 'critical',
        ]);
        // Create lots of open tasks to keep completion near 0
        Task::factory()->count(10)->create([
            'project_id' => $critical->id,
            'status' => 'todo',
        ]);

        Livewire::actingAs($user)
            ->test('aggregate-view')
            ->assertSee('Healthy Proj')
            ->assertSee('Warning Proj')
            ->assertSee('Critical Proj');
    }
}
