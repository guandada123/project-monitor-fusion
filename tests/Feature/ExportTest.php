<?php

use App\Exports\ExportEngine;
use App\Exports\ProjectExport;
use App\Exports\TaskExport;
use App\Exports\RiskExport;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Risk;
use App\Models\ExportHistory;
use function Pest\Laravel\actingAs;

// ─── ExportEngine Service ───

describe('ExportEngine', function () {
    beforeEach(function () {
        $user = User::factory()->admin()->create();
        actingAs($user);
    });

    it('can get entity types', function () {
        $engine = app(ExportEngine::class);
        $types = $engine->getEntityTypes();

        expect($types)->toHaveKeys(['project', 'task', 'risk']);
        expect($types['project'])->toBe('项目');
        expect($types['task'])->toBe('任务');
        expect($types['risk'])->toBe('风险');
    });

    it('can get available columns for project', function () {
        $engine = app(ExportEngine::class);
        $columns = $engine->getColumnsFor('project');

        expect($columns)->toHaveKey('name');
        expect($columns)->toHaveKey('status');
        expect($columns)->toHaveKey('owner.name');
        expect($columns)->toHaveKey('task_count');
    });

    it('can get available columns for task', function () {
        $engine = app(ExportEngine::class);
        $columns = $engine->getColumnsFor('task');

        expect($columns)->toHaveKey('title');
        expect($columns)->toHaveKey('project.name');
        expect($columns)->toHaveKey('assignee.name');
        expect($columns)->toHaveKey('priority');
    });

    it('can get available columns for risk', function () {
        $engine = app(ExportEngine::class);
        $columns = $engine->getColumnsFor('risk');

        expect($columns)->toHaveKey('title');
        expect($columns)->toHaveKey('severity');
        expect($columns)->toHaveKey('project.name');
    });

    it('returns empty array for invalid entity type', function () {
        $engine = app(ExportEngine::class);
        $columns = $engine->getColumnsFor('invalid_type');

        expect($columns)->toBe([]);
    });

    it('exports projects as csv', function () {
        Project::factory()->count(3)->create();

        $engine = app(ExportEngine::class);
        $history = $engine->export('project', 'csv');

        expect($history)->toBeInstanceOf(ExportHistory::class);
        expect($history->entity_type)->toBe('project');
        expect($history->format)->toBe('csv');
        expect($history->record_count)->toBe(3);
        expect($history->status)->toBe('completed');
        expect($history->file_path)->not->toBeNull();
        expect($history->file_size)->not->toBeNull();
        expect($history->completed_at)->not->toBeNull();

        // Verify file exists
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('exports projects as xlsx', function () {
        Project::factory()->count(2)->create();

        $engine = app(ExportEngine::class);
        $history = $engine->export('project', 'xlsx');

        expect($history->format)->toBe('xlsx');
        expect($history->record_count)->toBe(2);
        expect($history->status)->toBe('completed');
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('exports projects as pdf', function () {
        Project::factory()->count(3)->create();

        $engine = app(ExportEngine::class);
        $history = $engine->export('project', 'pdf');

        expect($history)->toBeInstanceOf(ExportHistory::class);
        expect($history->entity_type)->toBe('project');
        expect($history->format)->toBe('pdf');
        expect($history->record_count)->toBe(3);
        expect($history->status)->toBe('completed');
        expect($history->file_path)->not->toBeNull();
        expect($history->file_size)->not->toBeNull();
        expect($history->completed_at)->not->toBeNull();
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('exports tasks as csv', function () {
        $project = Project::factory()->create();
        Task::factory()->count(3)->create(['project_id' => $project->id]);

        $engine = app(ExportEngine::class);
        $history = $engine->export('task', 'csv');

        expect($history->entity_type)->toBe('task');
        expect($history->record_count)->toBe(3);
        expect($history->status)->toBe('completed');
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('exports risks as xlsx', function () {
        $project = Project::factory()->create();
        Risk::factory()->count(2)->create(['project_id' => $project->id]);

        $engine = app(ExportEngine::class);
        $history = $engine->export('risk', 'xlsx');

        expect($history->entity_type)->toBe('risk');
        expect($history->record_count)->toBe(2);
        expect($history->status)->toBe('completed');
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('exports risks as pdf', function () {
        $project = Project::factory()->create();
        Risk::factory()->count(2)->create(['project_id' => $project->id]);

        $engine = app(ExportEngine::class);
        $history = $engine->export('risk', 'pdf');

        expect($history->entity_type)->toBe('risk');
        expect($history->format)->toBe('pdf');
        expect($history->record_count)->toBe(2);
        expect($history->status)->toBe('completed');
        Storage::disk('local')->assertExists($history->file_path);
    });

    it('throws exception for invalid entity type', function () {
        $engine = app(ExportEngine::class);

        $engine->export('invalid_type', 'csv');
    })->throws(\InvalidArgumentException::class);

    it('throws exception for invalid format', function () {
        $engine = app(ExportEngine::class);

        $engine->export('project', 'invalid');
    })->throws(\InvalidArgumentException::class);

    it('records failed export in history', function () {
        $engine = app(ExportEngine::class);

        try {
            $engine->export('project', 'invalid');
        } catch (\Exception $e) {
            // Expected
        }

        // Verify latest history entry is failed
        $history = ExportHistory::where('format', 'invalid')->latest()->first();
        expect($history)->not->toBeNull();
        expect($history->status)->toBe('failed');
        expect($history->error_message)->not->toBeNull();
    });

    it('can get user export history', function () {
        $user = auth()->user();

        ExportHistory::factory()->count(5)->create(['user_id' => $user->id]);

        $engine = app(ExportEngine::class);
        $history = $engine->getHistory(10);

        expect($history)->toHaveCount(5);
        expect($history->first()->user_id)->toBe($user->id);
    });

    it('returns empty history for new user', function () {
        $engine = app(ExportEngine::class);
        $history = $engine->getHistory(10);

        expect($history)->toHaveCount(0);
    });
});

// ─── ExportPanel Livewire Component ───

describe('ExportPanel Livewire', function () {
    beforeEach(function () {
        $user = User::factory()->admin()->create();
        actingAs($user);
    });

    it('renders on dashboard', function () {
        \Pest\Laravel\get('/')
            ->assertStatus(200)
            ->assertSeeLivewire('export-panel');
    });

    it('can open panel', function () {
        \Livewire::test('export-panel')
            ->assertSet('showPanel', false)
            ->call('openPanel')
            ->assertSet('showPanel', true)
            ->assertSet('step', 'select');
    });

    it('can open panel with specific entity type', function () {
        \Livewire::test('export-panel')
            ->call('openPanel', 'risk')
            ->assertSet('entityType', 'risk');
    });

    it('ignores invalid entity type when opening', function () {
        \Livewire::test('export-panel')
            ->call('openPanel', 'invalid')
            ->assertSet('entityType', 'project');
    });

    it('can close panel', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->assertSet('showPanel', true)
            ->call('closePanel')
            ->assertSet('showPanel', false)
            ->assertSet('step', 'select');
    });

    it('switching entity type loads different columns', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->assertSet('entityType', 'project')
            ->set('entityType', 'task')
            ->assertSet('entityType', 'task')
            ->assertSet('availableColumns', function ($columns) {
                return array_key_exists('title', $columns);
            });
    });

    it('can toggle column selection', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->set('selectedColumns.name', false)
            ->assertSet('selectedColumns.name', false)
            ->set('selectedColumns.name', true)
            ->assertSet('selectedColumns.name', true);
    });

    it('can select all columns', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->set('selectedColumns.name', false)
            ->call('toggleAllColumns', true)
            ->assertSet('selectedColumns.name', true);
    });

    it('can deselect all columns', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('toggleAllColumns', false)
            ->assertSet('selectedColumns.name', false);
    });

    it('can start export for projects', function () {
        Project::factory()->count(3)->create();

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('startExport')
            ->assertSet('step', 'done')
            ->assertSet('exportProgress.entity_type', 'project')
            ->assertSet('exportProgress.record_count', 3)
            ->assertSet('exportProgress.status', 'completed')
            ->assertSet('exportProgress.file_path', function ($path) {
                return $path !== null && Storage::disk('local')->exists($path);
            });
    });

    it('can start export for tasks', function () {
        $project = Project::factory()->create();
        Task::factory()->count(2)->create(['project_id' => $project->id]);

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->set('entityType', 'task')
            ->call('startExport')
            ->assertSet('step', 'done')
            ->assertSet('exportProgress.entity_type', 'task')
            ->assertSet('exportProgress.record_count', 2);
    });

    it('can start export for risks', function () {
        $project = Project::factory()->create();
        Risk::factory()->count(4)->create(['project_id' => $project->id]);

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->set('entityType', 'risk')
            ->call('startExport')
            ->assertSet('step', 'done')
            ->assertSet('exportProgress.entity_type', 'risk')
            ->assertSet('exportProgress.record_count', 4);
    });

    it('shows error when no columns selected', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('toggleAllColumns', false)
            ->call('startExport')
            ->assertSet('errorMessage', '请至少选择一列进行导出')
            ->assertSet('step', 'select');
    });

    it('can reset export after completion', function () {
        Project::factory()->count(2)->create();

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('startExport')
            ->assertSet('step', 'done')
            ->call('resetExport')
            ->assertSet('step', 'select')
            ->assertSet('exportProgress', [])
            ->assertSet('errorMessage', null);
    });

    it('loads export history', function () {
        $user = auth()->user();
        ExportHistory::factory()->count(3)->create(['user_id' => $user->id]);

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->assertSet('history', function ($history) {
                return count($history) === 3;
            });
    });

    it('shows empty history for first-time user', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->assertSet('history', []);
    });

    it('records export in history after completion', function () {
        Project::factory()->count(2)->create();
        $user = auth()->user();

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('startExport');

        $historyCount = ExportHistory::where('user_id', $user->id)->count();
        expect($historyCount)->toBe(1);
    });

    it('can switch between csv and xlsx format', function () {
        \Livewire::test('export-panel')
            ->call('openPanel')
            ->assertSet('format', 'xlsx')
            ->set('format', 'csv')
            ->assertSet('format', 'csv');
    });

    it('can download exported file', function () {
        Project::factory()->count(1)->create();

        \Livewire::test('export-panel')
            ->call('openPanel')
            ->call('startExport')
            ->assertSet('step', 'done')
            ->assertSet('exportProgress.file_path', function ($path) {
                return $path !== null;
            });
    });
});

// ─── ExportHistory Model ───

describe('ExportHistory model', function () {
    it('can create export history record', function () {
        $user = User::factory()->admin()->create();

        $history = ExportHistory::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'project',
            'format' => 'csv',
            'record_count' => 10,
        ]);

        expect($history)->toBeInstanceOf(ExportHistory::class);
        expect($history->entity_type)->toBe('project');
        expect($history->format)->toBe('csv');
        expect($history->record_count)->toBe(10);
    });

    it('belongs to user', function () {
        $user = User::factory()->admin()->create();
        $history = ExportHistory::factory()->create(['user_id' => $user->id]);

        expect($history->user)->toBeInstanceOf(User::class);
        expect($history->user->id)->toBe($user->id);
    });

    it('returns correct entity label', function () {
        $user = User::factory()->admin()->create();

        $projectHistory = ExportHistory::factory()->create(['user_id' => $user->id, 'entity_type' => 'project']);
        $taskHistory = ExportHistory::factory()->create(['user_id' => $user->id, 'entity_type' => 'task']);
        $riskHistory = ExportHistory::factory()->create(['user_id' => $user->id, 'entity_type' => 'risk']);

        expect($projectHistory->entity_label)->toBe('项目');
        expect($taskHistory->entity_label)->toBe('任务');
        expect($riskHistory->entity_label)->toBe('风险');
    });

    it('returns correct format label', function () {
        $user = User::factory()->admin()->create();

        $csvHistory = ExportHistory::factory()->create(['user_id' => $user->id, 'format' => 'csv']);
        $xlsxHistory = ExportHistory::factory()->create(['user_id' => $user->id, 'format' => 'xlsx']);

        expect($csvHistory->format_label)->toBe('CSV');
        expect($xlsxHistory->format_label)->toBe('XLSX');
    });

    it('returns duration between created and completed', function () {
        $user = User::factory()->admin()->create();

        $history = ExportHistory::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        expect($history->duration)->not->toBeNull();
        expect($history->duration)->toContain('分钟');
    });

    it('returns null duration when not completed', function () {
        $user = User::factory()->admin()->create();

        $history = ExportHistory::factory()->create([
            'user_id' => $user->id,
            'completed_at' => null,
        ]);

        expect($history->duration)->toBeNull();
    });

    it('recent scope orders by created_at desc', function () {
        $user = User::factory()->admin()->create();

        ExportHistory::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);
        ExportHistory::factory()->create(['user_id' => $user->id, 'created_at' => now()]);
        ExportHistory::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(2)]);

        $recent = ExportHistory::recent()->get();
        expect($recent[0]->created_at->gt($recent[1]->created_at))->toBeTrue();
        expect($recent[1]->created_at->gt($recent[2]->created_at))->toBeTrue();
    });

    it('casts filters and selected_columns as array', function () {
        $user = User::factory()->admin()->create();

        $history = ExportHistory::factory()->create([
            'user_id' => $user->id,
            'filters' => ['status' => 'active'],
            'selected_columns' => ['name', 'status'],
        ]);

        expect($history->filters)->toBeArray();
        expect($history->filters['status'])->toBe('active');
        expect($history->selected_columns)->toBeArray();
        expect($history->selected_columns)->toContain('name');
    });
});

// ─── ProjectExport ───

describe('ProjectExport', function () {
    it('returns projects collection with eager loading', function () {
        Project::factory()->count(3)->create();

        $exporter = app(ProjectExport::class);
        $results = $exporter->query();

        expect($results)->toHaveCount(3);
    });

    it('has default columns', function () {
        $exporter = app(ProjectExport::class);

        $columns = $exporter->defaultColumns();
        expect($columns)->toHaveKeys(['name', 'slug', 'status', 'source', 'task_count', 'risk_count', 'created_at']);
    });

    it('has available columns', function () {
        $exporter = app(ProjectExport::class);

        $columns = $exporter->availableColumns();
        expect($columns)->toHaveKeys(['name', 'slug', 'status', 'source', 'description', 'owner.name', 'task_count', 'risk_count', 'start_at', 'end_at', 'completion_rate', 'overdue_rate', 'open_risk_count', 'created_at', 'updated_at']);
    });
});

// ─── TaskExport ───

describe('TaskExport', function () {
    it('returns tasks collection with eager loading', function () {
        $project = Project::factory()->create();
        Task::factory()->count(3)->create(['project_id' => $project->id]);

        $exporter = app(TaskExport::class);
        $results = $exporter->query();

        expect($results)->toHaveCount(3);
    });

    it('has default columns', function () {
        $exporter = app(TaskExport::class);

        $columns = $exporter->defaultColumns();
        expect($columns)->toHaveKeys(['title', 'project.name', 'assignee.name', 'status', 'priority', 'due_at', 'created_at']);
    });

    it('has available columns', function () {
        $exporter = app(TaskExport::class);

        $columns = $exporter->availableColumns();
        expect($columns)->toHaveKeys(['title', 'description', 'project.name', 'assignee.name', 'status', 'priority', 'estimate', 'spent', 'start_at', 'due_at', 'completed_at', 'is_overdue', 'tags', 'created_at', 'updated_at']);
    });
});

// ─── RiskExport ───

describe('RiskExport', function () {
    it('returns risks collection with eager loading', function () {
        $project = Project::factory()->create();
        Risk::factory()->count(3)->create(['project_id' => $project->id]);

        $exporter = app(RiskExport::class);
        $results = $exporter->query();

        expect($results)->toHaveCount(3);
    });

    it('has default columns', function () {
        $exporter = app(RiskExport::class);

        $columns = $exporter->defaultColumns();
        expect($columns)->toHaveKeys(['title', 'project.name', 'severity', 'status', 'rule_key', 'occurrence_count', 'last_seen_at']);
    });

    it('has available columns', function () {
        $exporter = app(RiskExport::class);

        $columns = $exporter->availableColumns();
        expect($columns)->toHaveKeys(['title', 'detail', 'project.name', 'severity', 'status', 'rule_key', 'occurrence_count', 'first_seen_at', 'last_seen_at', 'acknowledged_at', 'closed_at', 'suggestion', 'created_at']);
    });
});
