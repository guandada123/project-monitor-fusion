<?php

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ─── API: Token Management ───

test('api health check returns ok', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'OK',
        ]);
});

test('api token can be created via email and password', function () {
    $user = User::factory()->admin()->create([
        'email' => 'api_token_test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'name' => 'Test Token',
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Token 创建成功',
        ])
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_name',
                'user' => ['id', 'name', 'email'],
            ],
        ]);
});

test('api token creation fails with wrong password', function () {
    $user = User::factory()->admin()->create([
        'email' => 'api_wrong_pw@example.com',
        'password' => Hash::make('correct_password'),
    ]);

    $response = $this->postJson('/api/tokens', [
        'email' => $user->email,
        'password' => 'wrong_password',
        'name' => 'Bad Token',
    ]);

    $response->assertUnprocessable();
});

test('api tokens list requires authentication', function () {
    $this->getJson('/api/tokens')->assertUnauthorized();
});

test('api authenticated user can list their tokens', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('TestToken');

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/tokens');

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonCount(1, 'data');
});

test('api token can be revoked', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('Revocable');

    $response = $this->withToken($token->plainTextToken)
        ->deleteJson('/api/tokens/'.$token->accessToken->id);

    $response->assertOk()
        ->assertJson(['message' => 'Token 已撤销']);

    $this->assertEquals(0, $user->tokens()->count());
});

// ─── API: Projects ───

test('api authenticated user can list projects', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    Project::factory()->count(3)->create(['owner_id' => $user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/projects');

    $response->assertOk()
        ->assertJsonCount(3, 'data.data');
});

test('api create project with valid data', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/projects', [
            'name' => 'API Test Project',
            'description' => 'Created via API',
            'status' => 'active',
        ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => '项目创建成功',
        ])
        ->assertJsonPath('data.name', 'API Test Project');
});

test('api show project detail', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson("/api/projects/{$project->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $project->id);
});

test('api update project', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Old Name']);

    $response = $this->withToken($token->plainTextToken)
        ->putJson("/api/projects/{$project->id}", [
            'name' => 'Updated Name',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('api delete project soft-deletes it', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->deleteJson("/api/projects/{$project->id}");

    $response->assertOk();
    $this->assertSoftDeleted($project);
});

// ─── API: Tasks ───

test('api list tasks with filters', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->count(5)->create(['project_id' => $project->id]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/tasks?project_id='.$project->id);

    $response->assertOk()
        ->assertJsonCount(5, 'data.data');
});

test('api create task with valid data', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'API Task',
            'priority' => 'high',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'API Task');
});

test('api batch update task status', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $tasks = Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo']);

    $response = $this->withToken($token->plainTextToken)
        ->patchJson('/api/tasks/batch-status', [
            'ids' => $tasks->pluck('id')->toArray(),
            'status' => 'done',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.updated', 3);
});

// ─── API: Risks ───

test('api list risks with filters', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Risk::factory()->count(3)->create(['project_id' => $project->id, 'severity' => 'high']);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/risks?project_id='.$project->id);

    $response->assertOk()
        ->assertJsonCount(3, 'data.data');
});

test('api update risk status', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $risk = Risk::factory()->create(['status' => 'open']);

    $response = $this->withToken($token->plainTextToken)
        ->patchJson("/api/risks/{$risk->id}/status", [
            'status' => 'resolved',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'resolved');
});

// ─── API: Metrics ───

test('api list metrics', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/metrics');

    $response->assertOk();
});

// ─── API: Board ───

test('api board returns grouped tasks', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'todo']);
    Task::factory()->count(1)->create(['project_id' => $project->id, 'status' => 'done']);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/board?project_id='.$project->id);

    $response->assertOk()
        ->assertJsonCount(2, 'data.columns.todo')
        ->assertJsonCount(1, 'data.columns.done');
});

test('api board orders tasks by sort_order', function () {
    // 回归（2026-10-24）：sort_order 曾无迁移。SQLite 把不存在的双引号标识符
    // 当字符串字面量 → 排序静默失效且测试绿灯；pgsql 直接 500。
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => '第三', 'sort_order' => 30]);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => '第一', 'sort_order' => 10]);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => '第二', 'sort_order' => 20]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/board?project_id='.$project->id);

    $response->assertOk();
    $titles = collect($response->json('data.columns.todo'))->pluck('title')->all();

    expect($titles)->toBe(['第一', '第二', '第三']);
});

test('api reorder persists sort_order and affects board order', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $a = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => 'A', 'sort_order' => 10]);
    $b = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => 'B', 'sort_order' => 20]);

    $this->withToken($token->plainTextToken)
        ->putJson('/api/tasks/reorder', ['tasks' => [
            ['id' => $a->id, 'sort_order' => 50],
            ['id' => $b->id, 'sort_order' => 5],
        ]])
        ->assertOk();

    expect($a->fresh()->sort_order)->toBe(50)
        ->and($b->fresh()->sort_order)->toBe(5);

    $titles = collect(
        $this->withToken($token->plainTextToken)
            ->getJson('/api/board?project_id='.$project->id)
            ->json('data.columns.todo')
    )->pluck('title')->all();

    expect($titles)->toBe(['B', 'A']);
});

// ─── API: Aggregate ───

test('api aggregate returns cross-project stats', function () {
    $user = User::factory()->admin()->create();
    $token = $user->createToken('API');
    Project::factory()->count(2)->create(['owner_id' => $user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/aggregate');

    $response->assertOk()
        ->assertJsonPath('data.projects.total', 2);
});

// ─── API: Auth Protection ───

test('api unauthenticated requests are rejected', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
    $this->getJson('/api/tasks')->assertUnauthorized();
    $this->getJson('/api/risks')->assertUnauthorized();
    $this->getJson('/api/board')->assertUnauthorized();
});
