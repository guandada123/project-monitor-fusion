<?php

use App\Models\Metric;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\{getJson, postJson, putJson, patchJson, deleteJson};

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

// ════════════════════════════════════════════════════════════════
// API: Unauthenticated — all 6 endpoints must return 401
// ════════════════════════════════════════════════════════════════

test('api returns 401 without token on index', function () {
    getJson('/api/projects')->assertStatus(401);
});

test('api returns 401 without token on store', function () {
    postJson('/api/projects', ['name' => 'Test'])->assertStatus(401);
});

test('api returns 401 without token on show', function () {
    getJson('/api/projects/1')->assertStatus(401);
});

test('api returns 401 without token on update', function () {
    putJson('/api/projects/1', ['name' => 'Updated'])->assertStatus(401);
});

test('api returns 401 without token on destroy', function () {
    deleteJson('/api/projects/1')->assertStatus(401);
});

test('api returns 401 without token on updateStatus', function () {
    patchJson('/api/projects/1/status', ['status' => 'archived'])->assertStatus(401);
});

// ════════════════════════════════════════════════════════════════
// API: GET /api/projects — List projects
// ════════════════════════════════════════════════════════════════

test('admin can list all projects with pagination structure', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory(3)->create();

    $response = getJson('/api/projects');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['data', 'current_page', 'per_page', 'total', 'links'],
        ]);
});

test('projects index respects user access scoping for viewer', function () {
    $admin = User::factory()->admin()->create();
    Project::factory(3)->create(['owner_id' => $admin->id]);

    $viewer = User::factory()->viewer()->create();
    Project::factory(2)->create(['owner_id' => $viewer->id]);

    Sanctum::actingAs($viewer);
    $response = getJson('/api/projects');

    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(2);
});

test('projects index filters by status', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory(2)->create(['status' => 'active']);
    Project::factory(3)->create(['status' => 'archived']);

    $response = getJson('/api/projects?status=active');
    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(2);
});

test('projects index filters by source', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory(3)->create(['source' => 'manual']);
    Project::factory(2)->create(['source' => 'github']);

    $response = getJson('/api/projects?source=github');
    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(2);
});

test('projects index searches by name with LIKE', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory()->create(['name' => 'Alpha Project']);
    Project::factory()->create(['name' => 'Beta Project']);
    Project::factory()->create(['name' => 'Gamma System']);

    $response = getJson('/api/projects?search=Project');
    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(2);
});

test('projects index safely escapes LIKE wildcards in search', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory()->create(['name' => '100% Complete']);
    Project::factory()->create(['name' => 'Test Project']);

    $response = getJson('/api/projects?search=100%25');
    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(1);
});

test('projects index respects per_page parameter', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    Project::factory(10)->create();

    $response = getJson('/api/projects?per_page=3');
    $response->assertStatus(200);
    expect(count($response->json('data.data')))->toBe(3);
    expect($response->json('data.per_page'))->toBe(3);
});

test('projects index includes tasks_count and risks_count', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();
    Task::factory(3)->create(['project_id' => $project->id]);
    Risk::factory(2)->create(['project_id' => $project->id]);

    $response = getJson('/api/projects');
    $response->assertStatus(200);

    $first = $response->json('data.data')[0];
    expect((int) $first['tasks_count'])->toBe(3);
    expect((int) $first['risks_count'])->toBe(2);
});

// ════════════════════════════════════════════════════════════════
// API: POST /api/projects — Create project
// ════════════════════════════════════════════════════════════════

test('admin can create a project', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $response = postJson('/api/projects', [
        'name' => 'New Test Project',
        'description' => 'A test project description',
        'status' => 'active',
        'source' => 'manual',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['success', 'message', 'data']);
    expect($response->json('data.name'))->toBe('New Test Project');
    expect($response->json('data.slug'))->toBe('new-test-project');
    expect($response->json('data.owner_id'))->toBe($user->id);
});

test('store validates required name field', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $response = postJson('/api/projects', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store generates unique slugs for duplicate project names', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $response1 = postJson('/api/projects', ['name' => 'My Project']);
    $response1->assertStatus(201);
    expect($response1->json('data.slug'))->toBe('my-project');

    $response2 = postJson('/api/projects', ['name' => 'My Project']);
    $response2->assertStatus(201);
    expect($response2->json('data.slug'))->toBe('my-project-1');
});

test('store validates invalid status value', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $response = postJson('/api/projects', [
        'name' => 'Test',
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

// ════════════════════════════════════════════════════════════════
// API: GET /api/projects/{project} — Project detail
// ════════════════════════════════════════════════════════════════

test('admin can view project detail with relation counts', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory(2)->create(['project_id' => $project->id]);
    Risk::factory(1)->create(['project_id' => $project->id]);
    Metric::factory(3)->create(['project_id' => $project->id]);

    $response = getJson("/api/projects/{$project->id}");
    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data']);

    $data = $response->json('data');
    expect((int) $data['tasks_count'])->toBe(2);
    expect((int) $data['risks_count'])->toBe(1);
    expect((int) $data['metrics_count'])->toBe(3);
});

test('viewer cannot access project owned by another user', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::factory()->create(['owner_id' => $admin->id]);

    $viewer = User::factory()->viewer()->create();
    Sanctum::actingAs($viewer);

    $response = getJson("/api/projects/{$project->id}");
    // Viewer passes view_projects middleware but fails canAccessProject()
    $response->assertStatus(403);
});

test('show returns 404 for non-existent project', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    getJson('/api/projects/99999')->assertStatus(404);
});

// ════════════════════════════════════════════════════════════════
// API: PUT /api/projects/{project} — Update project
// ════════════════════════════════════════════════════════════════

test('admin can update project with slug regeneration on rename', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = putJson("/api/projects/{$project->id}", [
        'name' => 'Updated Project Name',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data']);
    expect($response->json('data.name'))->toBe('Updated Project Name');
    expect($response->json('data.slug'))->toBe('updated-project-name');
});

test('update validates status field', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();

    $response = putJson("/api/projects/{$project->id}", [
        'status' => 'invalid',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('user without manage_projects permission cannot update project', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::factory()->create(['owner_id' => $admin->id]);

    $viewer = User::factory()->viewer()->create();
    Sanctum::actingAs($viewer);

    $response = putJson("/api/projects/{$project->id}", [
        'name' => 'Hacked Name',
    ]);

    $response->assertStatus(403);
});

// ════════════════════════════════════════════════════════════════
// API: DELETE /api/projects/{project} — Delete project
// ════════════════════════════════════════════════════════════════

test('admin can soft-delete project without relations', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = deleteJson("/api/projects/{$project->id}");
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => '项目已删除']);

    $this->assertSoftDeleted($project);
});

test('destroy returns 409 when project has tasks', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();
    Task::factory(3)->create(['project_id' => $project->id]);

    $response = deleteJson("/api/projects/{$project->id}");
    $response->assertStatus(409)
        ->assertJson(['success' => false]);
});

test('destroy returns 409 when project has risks', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();
    Risk::factory(2)->create(['project_id' => $project->id]);

    $response = deleteJson("/api/projects/{$project->id}");
    $response->assertStatus(409)
        ->assertJson(['success' => false]);
});

test('destroy returns 409 when project has metrics', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();
    Metric::factory(3)->create(['project_id' => $project->id]);

    $response = deleteJson("/api/projects/{$project->id}");
    $response->assertStatus(409)
        ->assertJson(['success' => false]);
});

// ════════════════════════════════════════════════════════════════
// API: PATCH /api/projects/{project}/status — Update status
// ════════════════════════════════════════════════════════════════

test('admin can update project status to archived', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create(['status' => 'active']);

    $response = patchJson("/api/projects/{$project->id}/status", [
        'status' => 'archived',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'message', 'data']);
    expect($response->json('data.status'))->toBe('archived');
});

test('admin can update project status to planning', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create(['status' => 'active']);

    $response = patchJson("/api/projects/{$project->id}/status", [
        'status' => 'planning',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('planning');
});

test('updateStatus validates required status field', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();

    $response = patchJson("/api/projects/{$project->id}/status", []);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('updateStatus rejects invalid status value', function () {
    $user = User::factory()->admin()->create();
    Sanctum::actingAs($user);

    $project = Project::factory()->create();

    $response = patchJson("/api/projects/{$project->id}/status", [
        'status' => 'invalid',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});
