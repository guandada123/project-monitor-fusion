<?php

use App\Models\Event;
use App\Models\Integration;
use App\Models\Project;
use App\Models\Task;
use App\Services\GitHubService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

// ─────────────────────────────────────────────
// GitHubService — GitHub API 集成测试
// ─────────────────────────────────────────────

// ─── 构造函数 & 配置 ────────────────────────

describe('constructor and configuration', function () {

    test('constructs from integration model', function () {
        $integration = Integration::factory()->create([
            'type' => 'github',
            'status' => 'active',
            'config' => [
                'token' => 'gh_test_token',
                'org' => 'laravel',
                'repo' => 'laravel',
            ],
        ]);

        $service = new GitHubService($integration);

        // Should not throw on creation
        expect($service)->toBeInstanceOf(GitHubService::class);
    });

    test('constructs from direct array config', function () {
        $service = new GitHubService([
            'token' => 'gh_test_token',
            'org' => 'livewire',
            'repo' => 'livewire',
        ]);

        expect($service)->toBeInstanceOf(GitHubService::class);
    });

    test('parses org/repo from repo field with slash', function () {
        $service = new GitHubService([
            'token' => 'gh_token',
            'repo' => 'owner/my-project',
        ]);

        // Use reflection to verify org and repo are correctly set
        $ref = new ReflectionClass($service);
        $orgProp = $ref->getProperty('org');
        $orgProp->setAccessible(true);
        $repoProp = $ref->getProperty('repo');
        $repoProp->setAccessible(true);

        expect($orgProp->getValue($service))->toBe('owner');
        expect($repoProp->getValue($service))->toBe('my-project');
    });

    test('empty config has no apiBase', function () {
        $service = new GitHubService(['token' => '']);

        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty('apiBase');
        $prop->setAccessible(true);

        expect($prop->getValue($service))->toBeNull();
    });
});

// ─── requireApiBase ────────────────────────

describe('requireApiBase', function () {

    test('throws when apiBase is null', function () {
        $service = new GitHubService(['token' => '']);

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('requireApiBase');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($service))
            ->toThrow(RuntimeException::class, '缺少 GitHub org/repo');
    });
});

// ─── testConnection ────────────────────────

describe('testConnection', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('returns ok on successful connection', function () {
        Http::fake([
            'api.github.com/repos/test-org/test-repo' => Http::response([
                'full_name' => 'test-org/test-repo',
                'stargazers_count' => 42,
                'updated_at' => '2026-06-14T12:00:00Z',
            ], 200),
        ]);

        $service = new GitHubService([
            'token' => 'gh_token',
            'org' => 'test-org',
            'repo' => 'test-repo',
        ]);

        $result = $service->testConnection();

        expect($result['ok'])->toBeTrue();
        expect($result['repo'])->toBe('test-org/test-repo');
        expect($result['stars'])->toBe(42);
    });

    test('returns error on HTTP failure', function () {
        Http::fake([
            'api.github.com/repos/test-org/test-repo' => Http::response('Not Found', 404),
        ]);

        $service = new GitHubService([
            'token' => 'gh_token',
            'org' => 'test-org',
            'repo' => 'test-repo',
        ]);

        $result = $service->testConnection();

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('404');
    });

    test('returns error on network exception', function () {
        Http::fake([
            'api.github.com/repos/test-org/test-repo' => function () {
                throw new ConnectionException('cURL error 6: Could not resolve host');
            },
        ]);

        $service = new GitHubService([
            'token' => 'gh_token',
            'org' => 'test-org',
            'repo' => 'test-repo',
        ]);

        $result = $service->testConnection();

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('网络连接失败');
    });

    test('returns error when org/repo missing', function () {
        $service = new GitHubService([]);

        $result = $service->testConnection();

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('缺少');
    });

    test('uses token in authorization header', function () {
        Http::fake([
            'api.github.com/repos/org/repo' => Http::response(['full_name' => 'org/repo', 'stargazers_count' => 0], 200),
        ]);

        $service = new GitHubService([
            'token' => 'gh_secret_123',
            'org' => 'org',
            'repo' => 'repo',
        ]);

        $service->testConnection();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && $request->header('Authorization')[0] === 'Bearer gh_secret_123';
        });
    });
});

// ─── fetchIssues / fetchPullRequests / fetchCommits ───

describe('data fetching methods', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('fetchIssues returns paginated issues', function () {
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response([
                ['number' => 1, 'title' => 'First issue', 'state' => 'open', 'labels' => []],
                ['number' => 2, 'title' => 'Second issue', 'state' => 'closed', 'labels' => []],
            ], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);

        $issues = $service->fetchIssues('all', 10);

        expect($issues)->toHaveCount(2);
        expect($issues[0]['number'])->toBe(1);
        expect($issues[1]['title'])->toBe('Second issue');
    });

    test('fetchIssues passes correct query parameters', function () {
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response([], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);
        $service->fetchIssues('open', 5);

        Http::assertSent(function ($request) {
            $query = $request->url();

            return str_contains($query, 'state=open')
                && str_contains($query, 'per_page=5');
        });
    });

    test('fetchPullRequests returns PR list', function () {
        Http::fake([
            'api.github.com/repos/org/repo/pulls*' => Http::response([
                ['number' => 10, 'title' => 'Add feature', 'state' => 'open', 'head' => ['ref' => 'feat'], 'base' => ['ref' => 'main']],
            ], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);

        $prs = $service->fetchPullRequests('all', 20);

        expect($prs)->toHaveCount(1);
        expect($prs[0]['number'])->toBe(10);
    });

    test('fetchCommits returns commit list', function () {
        Http::fake([
            'api.github.com/repos/org/repo/commits*' => Http::response([
                ['sha' => 'abc123', 'commit' => ['message' => 'fix bug', 'author' => ['name' => 'Dev', 'date' => '2026-06-14T10:00:00Z']]],
                ['sha' => 'def456', 'commit' => ['message' => 'add test', 'author' => ['name' => 'QA', 'date' => '2026-06-14T11:00:00Z']]],
            ], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);

        $commits = $service->fetchCommits('main', 10);

        expect($commits)->toHaveCount(2);
        expect($commits[0]['sha'])->toBe('abc123');
        expect($commits[1]['commit']['message'])->toBe('add test');
    });

    test('fetchCommits with branch sends sha parameter', function () {
        Http::fake([
            'api.github.com/repos/org/repo/commits*' => Http::response([], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);
        $service->fetchCommits('develop');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sha=develop');
        });
    });

    test('throws on API error response', function () {
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response('Forbidden', 403),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);

        expect(fn () => $service->fetchIssues())
            ->toThrow(RuntimeException::class, '403');
    });
});

// ─── detectPriority ────────────────────────

describe('detectPriority from labels', function () {

    function invokeDetectPriority(array $labels): string
    {
        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $ref = new ReflectionClass(GitHubService::class);
        $method = $ref->getMethod('detectPriority');
        $method->setAccessible(true);

        return $method->invoke($service, $labels);
    }

    test('critical labels map to critical priority', function () {
        expect(invokeDetectPriority(['bug', 'critical']))->toBe('critical');
        expect(invokeDetectPriority(['p0']))->toBe('critical');
        expect(invokeDetectPriority(['blocker']))->toBe('critical');
        expect(invokeDetectPriority(['emergency']))->toBe('critical');
    });

    test('high priority labels', function () {
        expect(invokeDetectPriority(['high']))->toBe('high');
        expect(invokeDetectPriority(['p1']))->toBe('high');
        expect(invokeDetectPriority(['important']))->toBe('high');
    });

    test('low priority labels', function () {
        expect(invokeDetectPriority(['low']))->toBe('low');
        expect(invokeDetectPriority(['p3']))->toBe('low');
        expect(invokeDetectPriority(['minor']))->toBe('low');
        expect(invokeDetectPriority(['nice-to-have']))->toBe('low');
    });

    test('unknown labels default to medium', function () {
        expect(invokeDetectPriority(['bug', 'enhancement']))->toBe('medium');
        expect(invokeDetectPriority([]))->toBe('medium');
    });

    test('priority detection is case-insensitive', function () {
        expect(invokeDetectPriority(['CRITICAL']))->toBe('critical');
        expect(invokeDetectPriority(['P0']))->toBe('critical');
        expect(invokeDetectPriority(['High']))->toBe('high');
    });
});

// ─── parseNextLink ──────────────────────────

describe('parseNextLink', function () {

    function invokeParseNextLink(?string $linkHeader): ?string
    {
        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $ref = new ReflectionClass(GitHubService::class);
        $method = $ref->getMethod('parseNextLink');
        $method->setAccessible(true);

        return $method->invoke($service, $linkHeader);
    }

    test('returns null for null header', function () {
        expect(invokeParseNextLink(null))->toBeNull();
    });

    test('returns null for empty string header', function () {
        expect(invokeParseNextLink(''))->toBeNull();
    });

    test('extracts next URL from single rel="next" link', function () {
        $header = '<https://api.github.com/repos/o/r/issues?page=2&per_page=30>; rel="next"';

        $result = invokeParseNextLink($header);

        expect($result)->toBe('https://api.github.com/repos/o/r/issues?page=2&per_page=30');
    });

    test('extracts next URL from multiple rel links', function () {
        $header = '<https://api.github.com/repos/o/r/issues?page=1>; rel="first",'
            .'<https://api.github.com/repos/o/r/issues?page=2>; rel="next",'
            .'<https://api.github.com/repos/o/r/issues?page=5>; rel="last"';

        $result = invokeParseNextLink($header);

        expect($result)->toBe('https://api.github.com/repos/o/r/issues?page=2');
    });

    test('returns null when no rel="next" link present', function () {
        $header = '<https://api.github.com/repos/o/r/issues?page=1>; rel="first",'
            .'<https://api.github.com/repos/o/r/issues?page=5>; rel="last"';

        expect(invokeParseNextLink($header))->toBeNull();
    });
});

// ─── getPaginated (multi-page traversal) ────

describe('getPaginated multi-page traversal', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('single page returns data without extra requests (backward compat)', function () {
        Http::fake([
            'api.github.com/repos/o/r/issues*' => Http::response([
                ['number' => 1, 'title' => 'Issue 1'],
                ['number' => 2, 'title' => 'Issue 2'],
            ], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $issues = $service->fetchIssues('all', 30);

        expect($issues)->toHaveCount(2);
        expect($issues[0]['number'])->toBe(1);
    });

    test('merges data from two pages via Link header', function () {
        $page1Body = [['number' => 1, 'title' => 'Issue 1']];
        $page2Body = [['number' => 2, 'title' => 'Issue 2']];

        Http::fake([
            'api.github.com/repos/o/r/issues*' => Http::sequence()
                ->push($page1Body, 200, [
                    'Link' => '<https://api.github.com/repos/o/r/issues?page=2>; rel="next"',
                ])
                ->push($page2Body, 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $issues = $service->fetchIssues('all', 30);

        expect($issues)->toHaveCount(2);
        expect($issues[0]['number'])->toBe(1);
        expect($issues[1]['number'])->toBe(2);
    });

    test('respects maxPages limit and stops early', function () {
        // Stub 12 pages in a cascading chain — default maxPages=10 should stop at page 10
        $sequence = Http::sequence();
        for ($i = 0; $i < 12; $i++) {
            $sequence->push([['number' => $i + 1]], 200, [
                'Link' => '<https://api.github.com/repos/o/r/issues?page='.($i + 2).'>; rel="next"',
            ]);
        }

        Http::fake([
            'api.github.com/repos/o/r/issues*' => $sequence,
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $issues = $service->fetchIssues('all', 30);

        // Default maxPages=10 → at most 10 page fetches
        expect($issues)->toHaveCount(10);
    });

    test('Http::assertSent confirms actual request count for multi-page', function () {
        Http::fake([
            'api.github.com/repos/o/r/issues*' => Http::sequence()
                ->push([['number' => 1]], 200, [
                    'Link' => '<https://api.github.com/repos/o/r/issues?page=2>; rel="next"',
                ])
                ->push([['number' => 2]], 200, [
                    'Link' => '<https://api.github.com/repos/o/r/issues?page=3>; rel="next"',
                ])
                ->push([['number' => 3]], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $issues = $service->fetchIssues('all', 30);

        expect($issues)->toHaveCount(3);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'repos/o/r/issues');
        });
    });
});

// ─── upsertIssue / upsertPullRequest / upsertCommitEvent ───

describe('entity upsert methods', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('upsertIssue creates new task from issue data', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        $ghIssue = [
            'number' => 42,
            'title' => 'Fix login bug',
            'state' => 'open',
            'body' => 'Users cannot log in when...',
            'labels' => [['name' => 'bug'], ['name' => 'high']],
            'html_url' => 'https://github.com/o/r/issues/42',
            'created_at' => '2026-06-14T10:00:00Z',
            'user' => ['login' => 'dev1'],
            'assignees' => [['login' => 'dev2']],
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertIssue');
        $method->setAccessible(true);

        $created = $method->invoke($service, $project, $ghIssue);

        expect($created)->toBe(1); // 1 = created

        $task = Task::where('project_id', $project->id)
            ->where('external_id', '42')
            ->first();

        expect($task)->not->toBeNull();
        expect($task->title)->toBe('Fix login bug');
        expect($task->status)->toBe('todo');
        expect($task->priority)->toBe('high');
        expect($task->tags)->toContain('bug');
        expect($task->meta['gh_url'])->toBe('https://github.com/o/r/issues/42');
    });

    test('upsertIssue updates existing task', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        // Pre-create the task
        Task::factory()->create([
            'project_id' => $project->id,
            'external_id' => '42',
            'title' => 'Old title',
            'status' => 'in_progress',
        ]);

        $ghIssue = [
            'number' => 42,
            'title' => 'Updated title',
            'state' => 'closed',
            'body' => 'Fixed',
            'labels' => [],
            'html_url' => '',
            'closed_at' => '2026-06-14T12:00:00Z',
            'created_at' => '2026-06-14T10:00:00Z',
            'user' => ['login' => 'dev1'],
            'milestone' => null,
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertIssue');
        $method->setAccessible(true);

        $updated = $method->invoke($service, $project, $ghIssue);

        expect($updated)->toBe(0); // 0 = updated

        $task = Task::where('project_id', $project->id)
            ->where('external_id', '42')
            ->first();

        expect($task->title)->toBe('Updated title');
        expect($task->status)->toBe('done'); // closed → done
        expect($task->completed_at)->not->toBeNull();
    });

    test('upsertPullRequest creates new task with PR prefix', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        $pr = [
            'number' => 100,
            'title' => 'Add new API endpoint',
            'state' => 'open',
            'body' => 'Implements the new endpoint...',
            'labels' => [['name' => 'enhancement']],
            'html_url' => 'https://github.com/o/r/pull/100',
            'created_at' => '2026-06-14T09:00:00Z',
            'user' => ['login' => 'pr-author'],
            'head' => ['ref' => 'feature/api'],
            'base' => ['ref' => 'main'],
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertPullRequest');
        $method->setAccessible(true);

        $created = $method->invoke($service, $project, $pr);

        expect($created)->toBe(1);

        $task = Task::where('project_id', $project->id)
            ->where('external_id', 'PR-100')
            ->first();

        expect($task)->not->toBeNull();
        expect($task->title)->toContain('[PR]');
        expect($task->status)->toBe('in_progress');
        expect($task->tags)->toContain('pr');
        expect($task->tags)->toContain('branch:feature/api');
    });

    test('upsertPullRequest sets done status for merged PR', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        $pr = [
            'number' => 101,
            'title' => 'Merged PR',
            'state' => 'closed',
            'merged_at' => '2026-06-14T11:00:00Z',
            'body' => '',
            'labels' => [],
            'html_url' => '',
            'created_at' => '2026-06-14T08:00:00Z',
            'user' => ['login' => 'author'],
            'head' => ['ref' => 'feat'],
            'base' => ['ref' => 'main'],
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertPullRequest');
        $method->setAccessible(true);

        $method->invoke($service, $project, $pr);

        $task = Task::where('project_id', $project->id)
            ->where('external_id', 'PR-101')
            ->first();

        expect($task->status)->toBe('done');
    });

    test('upsertCommitEvent creates event from commit', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        $commit = [
            'sha' => 'a1b2c3d4',
            'commit' => [
                'message' => 'Fix critical bug in auth',
                'author' => ['name' => 'John', 'email' => 'john@test.com', 'date' => '2026-06-14T10:30:00Z'],
            ],
            'html_url' => 'https://github.com/o/r/commit/a1b2c3d4',
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertCommitEvent');
        $method->setAccessible(true);

        $created = $method->invoke($service, $project, $commit);

        expect($created)->toBe(1);

        $event = Event::where('fingerprint', 'github-commit-a1b2c3d4')->first();
        expect($event)->not->toBeNull();
        expect($event->event_type)->toBe('commit');
        expect($event->payload['message'])->toBe('Fix critical bug in auth');
        expect($event->payload['author'])->toBe('John');
    });

    test('upsertCommitEvent is idempotent (same sha returns 0)', function () {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $service = new GitHubService(['token' => 't', 'org' => 'o', 'repo' => 'r']);
        $project = Project::factory()->create(['source' => 'github']);

        $commit = [
            'sha' => 'dup123',
            'commit' => [
                'message' => 'Duplicate',
                'author' => ['name' => 'Bot', 'email' => '', 'date' => '2026-06-14T10:00:00Z'],
            ],
            'html_url' => '',
        ];

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('upsertCommitEvent');
        $method->setAccessible(true);

        // First call creates
        $first = $method->invoke($service, $project, $commit);
        // Second call returns 0 (already exists)
        $second = $method->invoke($service, $project, $commit);

        expect($first)->toBe(1);
        expect($second)->toBe(0);

        // Only one event in DB
        expect(Event::where('fingerprint', 'github-commit-dup123')->count())->toBe(1);
    });
});

// ─── sync orchestration ────────────────────

describe('sync orchestration', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('sync creates tasks and events from GitHub data', function () {
        // Mock all three API endpoints
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response([
                [
                    'number' => 1, 'title' => 'Bug report', 'state' => 'open',
                    'body' => 'Details', 'labels' => [['name' => 'bug']],
                    'html_url' => '', 'created_at' => now()->toIsoString(),
                    'user' => ['login' => 'dev'], 'milestone' => null,
                ],
                [
                    'number' => 2, 'title' => 'Another issue', 'state' => 'closed',
                    'body' => '', 'labels' => [], 'html_url' => '', 'closed_at' => now()->toIsoString(),
                    'created_at' => now()->toIsoString(), 'user' => ['login' => 'dev'], 'milestone' => null,
                ],
            ], 200),
            'api.github.com/repos/org/repo/pulls*' => Http::response([
                [
                    'number' => 10, 'title' => 'Add feature', 'state' => 'open',
                    'body' => '', 'labels' => [], 'html_url' => '', 'created_at' => now()->toIsoString(),
                    'user' => ['login' => 'dev'],
                    'head' => ['ref' => 'feat'], 'base' => ['ref' => 'main'],
                ],
            ], 200),
            'api.github.com/repos/org/repo/commits*' => Http::response([
                [
                    'sha' => 'commit1', 'commit' => ['message' => 'Initial', 'author' => ['name' => 'Dev', 'email' => '', 'date' => now()->toIsoString()]],
                    'html_url' => '',
                ],
                [
                    'sha' => 'commit2', 'commit' => ['message' => 'Fix', 'author' => ['name' => 'Dev', 'email' => '', 'date' => now()->toIsoString()]],
                    'html_url' => '',
                ],
            ], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);
        $project = Project::factory()->create(['source' => 'github']);

        $result = $service->sync($project);

        expect($result['ok'])->toBeTrue();

        // Verify stats
        expect($result['stats']['issues'])->toBe(2); // 2 issues created
        expect($result['stats']['pull_requests'])->toBe(1); // 1 PR created
        expect($result['stats']['events'])->toBe(2); // 2 commit events created
        expect($result['stats']['commits'])->toBe(2); // 2 commits fetched

        // Verify DB records
        expect(Task::where('project_id', $project->id)->count())->toBe(3); // 2 issues + 1 PR
        expect(Event::where('project_id', $project->id)->count())->toBe(2); // 2 commits
    });

    test('sync skips pull requests in issues response', function () {
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response([
                [
                    'number' => 1, 'title' => 'Real issue', 'state' => 'open',
                    'body' => '', 'labels' => [], 'html_url' => '', 'created_at' => now()->toIsoString(),
                    'user' => ['login' => 'dev'], 'milestone' => null,
                ],
                [
                    'number' => 2, 'title' => 'PR disguised as issue', 'state' => 'open',
                    'pull_request' => ['url' => 'https://api.github.com/repos/org/repo/pulls/2'],
                    'body' => '', 'labels' => [], 'html_url' => '', 'created_at' => now()->toIsoString(),
                    'user' => ['login' => 'dev'], 'milestone' => null,
                ],
            ], 200),
            'api.github.com/repos/org/repo/pulls*' => Http::response([], 200),
            'api.github.com/repos/org/repo/commits*' => Http::response([], 200),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);
        $project = Project::factory()->create(['source' => 'github']);

        $result = $service->sync($project);

        // Only the real issue (not the PR) should be created
        expect($result['stats']['issues'])->toBe(1);
        expect(Task::where('project_id', $project->id)->count())->toBe(1);
        expect(Task::first()->title)->toBe('Real issue');
    });

    test('sync returns error on failure with partial stats', function () {
        Http::fake([
            'api.github.com/repos/org/repo/issues*' => Http::response('Rate Limit Exceeded', 403),
        ]);

        $service = new GitHubService(['token' => 't', 'org' => 'org', 'repo' => 'repo']);
        $project = Project::factory()->create(['source' => 'github']);

        $result = $service->sync($project);

        expect($result['ok'])->toBeFalse();
        expect($result['error'])->toContain('403');
        expect($result['stats'])->toHaveKey('issues');
    });
});

// ─── GitHubSyncCommand ─────────────────────

describe('github:sync command', function () {

    beforeEach(function () {
        Http::preventStrayRequests();
    });

    test('command fails when no integration configured', function () {
        $exitCode = Artisan::call('github:sync');

        expect($exitCode)->toBe(1);
        $output = Artisan::output();
        expect($output)->toContain('未找到');
    });

    test('command fails when integration exists but no project', function () {
        Integration::factory()->github()->active()->create([
            'config' => [
                'token' => 'gh_token',
                'org' => 'org',
                'repo' => 'repo',
            ],
        ]);

        $exitCode = Artisan::call('github:sync');

        expect($exitCode)->toBe(1);
        $output = Artisan::output();
        expect($output)->toContain('未找到关联项目');
    });

    test('command runs with dry-run flag and previews data', function () {
        Http::fake([
            'api.github.com/repos/org/repo' => Http::response([
                'full_name' => 'org/repo', 'stargazers_count' => 10,
            ], 200),
            'api.github.com/repos/org/repo/issues*' => Http::response([
                ['number' => 1, 'title' => 'Test issue', 'state' => 'open', 'pull_request' => null],
            ], 200),
        ]);

        Integration::factory()->github()->active()->create([
            'config' => ['token' => 't', 'org' => 'org', 'repo' => 'repo'],
        ]);
        Project::factory()->create(['slug' => 'test-project', 'source' => 'github']);

        $exitCode = Artisan::call('github:sync', [
            '--dry-run' => true,
            '--project' => 'test-project',
        ]);

        expect($exitCode)->toBe(0);
        $output = Artisan::output();
        expect($output)->toContain('DRY-RUN');
        expect($output)->toContain('#1');
        expect($output)->toContain('Test issue');
    });

    test('command performs full sync and reports stats', function () {
        Http::fake([
            'api.github.com/repos/org/repo' => Http::response([
                'full_name' => 'org/repo', 'stargazers_count' => 10,
            ], 200),
            'api.github.com/repos/org/repo/issues*' => Http::response([], 200),
            'api.github.com/repos/org/repo/pulls*' => Http::response([], 200),
            'api.github.com/repos/org/repo/commits*' => Http::response([], 200),
        ]);

        Integration::factory()->github()->active()->create([
            'config' => ['token' => 't', 'org' => 'org', 'repo' => 'repo'],
        ]);
        Project::factory()->create(['slug' => 'sync-project', 'source' => 'github']);

        $exitCode = Artisan::call('github:sync', [
            '--project' => 'sync-project',
        ]);

        expect($exitCode)->toBe(0);
        $output = Artisan::output();
        expect($output)->toContain('同步完成');
        expect($output)->toContain('Commits');
        expect($output)->toContain('Issues');
        expect($output)->toContain('Pull Requests');
    });

    test('command retries on failure up to max retries', function () {
        Http::fake([
            'api.github.com/repos/org/repo' => Http::response('Server Error', 500),
        ]);

        Integration::factory()->github()->active()->create([
            'config' => ['token' => 't', 'org' => 'org', 'repo' => 'repo'],
        ]);
        Project::factory()->create(['slug' => 'fail-project', 'source' => 'github']);

        $exitCode = Artisan::call('github:sync', [
            '--project' => 'fail-project',
        ]);

        expect($exitCode)->toBe(1);
        $output = Artisan::output();
        // Should have attempted 3 retries
        expect($output)->toContain('第 1/3');
        expect($output)->toContain('第 2/3');
        expect($output)->toContain('第 3/3');
        expect($output)->toContain('已达最大重试次数');
    });
});
