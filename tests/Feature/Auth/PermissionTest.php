<?php

use App\Models\Metric;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Livewire\MetricBuilder;
use App\Livewire\RiskManager;
use App\Livewire\RoleManager;
use App\Livewire\TaskBoard;
use Livewire\Livewire;

// ─────────────────────────────────────────────
// User Model — Permission Methods
// ─────────────────────────────────────────────

test('admin user has all permissions', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->hasPermission('view_dashboard'))->toBeTrue();
    expect($admin->hasPermission('manage_risks'))->toBeTrue();
    expect($admin->hasPermission('manage_settings'))->toBeTrue();
    expect($admin->hasPermission('manage_roles'))->toBeTrue();
});

test('viewer user has view permissions but no manage permissions', function () {
    $viewer = User::factory()->viewer()->create();

    expect($viewer->hasPermission('view_dashboard'))->toBeTrue();
    expect($viewer->hasPermission('view_risks'))->toBeTrue();
    expect($viewer->hasPermission('view_projects'))->toBeTrue();
    expect($viewer->hasPermission('view_reports'))->toBeTrue();

    expect($viewer->hasPermission('manage_risks'))->toBeFalse();
    expect($viewer->hasPermission('manage_metrics'))->toBeFalse();
    expect($viewer->hasPermission('manage_roles'))->toBeFalse();
    expect($viewer->hasPermission('manage_integrations'))->toBeFalse();
    expect($viewer->hasPermission('manage_settings'))->toBeFalse();
});

test('hasAnyPermission returns true if user has any of the given permissions', function () {
    $viewer = User::factory()->viewer()->create();

    expect($viewer->hasAnyPermission(['manage_risks', 'manage_metrics', 'view_reports']))->toBeTrue();
    expect($viewer->hasAnyPermission(['manage_risks', 'manage_metrics']))->toBeFalse();
});

test('hasAllPermissions returns true only if user has all given permissions', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->hasAllPermissions(['view_dashboard', 'manage_risks', 'manage_roles']))->toBeTrue();
    expect($admin->hasAllPermissions(['view_dashboard', 'nonexistent_perm']))->toBeFalse();
});

// ─────────────────────────────────────────────
// AuthorizesResourceAccess Trait
// ─────────────────────────────────────────────

test('unauthenticated user gets 403 from trait', function () {
    // No actingAs — user is unauthenticated
    // Calling any action that calls authorizeResourceAccess should abort
    $project = Project::factory()->create();
    $risk = Risk::factory()->create(['project_id' => $project->id]);

    Livewire::test(RiskManager::class)
        ->call('acknowledge', $risk->id)
        ->assertForbidden();
});

test('viewer without manage permission gets 403', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $project = Project::factory()->create();
    $risk = Risk::factory()->create(['project_id' => $project->id]);

    Livewire::test(RiskManager::class)
        ->call('acknowledge', $risk->id)
        ->assertForbidden();
});

test('admin with correct permission passes trait check', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $project = Project::factory()->create();
    $risk = Risk::factory()->create(['project_id' => $project->id]);

    // Should not abort — trait check passes
    Livewire::test(RiskManager::class)
        ->call('acknowledge', $risk->id)
        ->assertSuccessful();
});

// ─────────────────────────────────────────────
// Component Action Guards
// ─────────────────────────────────────────────

test('RiskManager viewer cannot dismiss risks', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $project = Project::factory()->create();
    $risk = Risk::factory()->create(['project_id' => $project->id]);

    Livewire::test(RiskManager::class)
        ->call('dismiss')
        ->assertForbidden();
});

test('RiskManager viewer cannot generate suggestions', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $project = Project::factory()->create();
    $risk = Risk::factory()->create(['project_id' => $project->id]);

    Livewire::test(RiskManager::class)
        ->call('generateSuggestion', $risk->id)
        ->assertForbidden();
});

test('RiskManager viewer cannot bulk acknowledge', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    Livewire::test(RiskManager::class)
        ->set('selectedIds', [1])
        ->call('bulkAcknowledge')
        ->assertForbidden();
});

test('TaskBoard viewer cannot move tasks', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $project = Project::factory()->create(['name' => '测试项目']);
    $task = Task::factory()->create(['project_id' => $project->id]);

    // We need a project loaded first, mount auto-selects first project
    Livewire::test(TaskBoard::class)
        ->call('moveTask', $task->id, 'done')
        ->assertForbidden();
});

test('MetricBuilder viewer cannot edit metrics', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $metric = Metric::factory()->create();

    Livewire::test(MetricBuilder::class)
        ->call('edit', $metric->id)
        ->assertForbidden();
});

test('MetricBuilder viewer cannot delete metrics', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $metric = Metric::factory()->create();

    Livewire::test(MetricBuilder::class)
        ->call('delete', $metric->id)
        ->assertForbidden();
});

test('RoleManager viewer cannot start edit role', function () {
    $viewer = User::factory()->viewer()->create();
    $this->actingAs($viewer);

    $role = Role::where('slug', 'viewer')->first();

    Livewire::test(RoleManager::class)
        ->call('startEdit', $role->id)
        ->assertForbidden();
});

test('RoleManager admin can start edit role', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $role = Role::where('slug', 'viewer')->first();

    Livewire::test(RoleManager::class)
        ->call('startEdit', $role->id)
        ->assertSet('editingRole', $role->id);
});

// ─────────────────────────────────────────────
// User Factory Role Assignment
// ─────────────────────────────────────────────

test('admin factory assigns admin role', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->hasRole('admin'))->toBeTrue();
    expect($admin->roles)->toHaveCount(1);
});
