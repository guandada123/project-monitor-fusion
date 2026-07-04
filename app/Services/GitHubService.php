<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Integration;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    protected string $token;

    protected string $org;

    protected string $repo;

    protected string $baseUrl;

    protected ?string $apiBase = null;

    public function __construct(array|Integration $config)
    {
        if ($config instanceof Integration) {
            $cfg = $config->config ?? [];
            $this->token = $cfg['token'] ?? '';
            $this->org = $cfg['org'] ?? '';
            $this->repo = $cfg['repo'] ?? '';
        } else {
            $this->token = $config['token'] ?? '';
            $this->org = $config['org'] ?? '';
            $this->repo = $config['repo'] ?? '';
        }

        // Support "org/repo" format in repo field
        if (empty($this->org) && str_contains($this->repo, '/')) {
            [$this->org, $this->repo] = explode('/', $this->repo, 2);
        }

        $this->baseUrl = 'https://api.github.com';
        if ($this->org && $this->repo) {
            $this->apiBase = "{$this->baseUrl}/repos/{$this->org}/{$this->repo}";
        }
    }

    // ─── Connection Test ───────────────────────────────────────────────

    public function testConnection(): array
    {
        try {
            $apiBase = $this->requireApiBase();
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $resp = $this->client()->get($apiBase);
            if ($resp->successful()) {
                $data = $resp->json();

                return [
                    'ok' => true,
                    'repo' => $data['full_name'] ?? "{$this->org}/{$this->repo}",
                    'stars' => $data['stargazers_count'] ?? 0,
                    'updated' => $data['updated_at'] ?? null,
                ];
            }

            return ['ok' => false, 'error' => "HTTP {$resp->status()}: {$resp->body()}"];
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => '网络连接失败: '.$e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Data Fetching ─────────────────────────────────────────────────

    public function fetchCommits(string $branch = '', int $perPage = 30): array
    {
        $url = $this->requireApiBase().'/commits';
        $params = ['per_page' => $perPage];
        if ($branch) {
            $params['sha'] = $branch;
        }

        return $this->getPaginated($url, $params);
    }

    public function fetchPullRequests(string $state = 'all', int $perPage = 20): array
    {
        $url = $this->requireApiBase().'/pulls';

        return $this->getPaginated($url, [
            'state' => $state,
            'per_page' => $perPage,
            'sort' => 'updated',
            'direction' => 'desc',
        ]);
    }

    public function fetchIssues(string $state = 'all', int $perPage = 30): array
    {
        $url = $this->requireApiBase().'/issues';

        return $this->getPaginated($url, [
            'state' => $state,
            'per_page' => $perPage,
            'sort' => 'updated',
            'direction' => 'desc',
            'filter' => 'all',
        ]);
    }

    public function fetchContributors(int $perPage = 20): array
    {
        $url = $this->requireApiBase().'/contributors';

        return $this->getPaginated($url, ['per_page' => $perPage]);
    }

    // ─── Sync Orchestration ────────────────────────────────────────────

    /**
     * Full sync: commits → Events, issues → Tasks, PRs → Tasks
     */
    public function sync(Project $project): array
    {
        $stats = ['commits' => 0, 'issues' => 0, 'pull_requests' => 0, 'events' => 0];

        try {
            // Sync recent issues (excluding PRs — GitHub API returns PRs as issues)
            $issues = $this->fetchIssues('all', 30);
            foreach ($issues as $issue) {
                if (! empty($issue['pull_request'])) {
                    continue;
                } // skip PRs
                $stats['issues'] += $this->upsertIssue($project, $issue);
            }

            // Sync PRs
            $prs = $this->fetchPullRequests('all', 20);
            foreach ($prs as $pr) {
                $stats['pull_requests'] += $this->upsertPullRequest($project, $pr);
            }

            // Sync recent commits as events
            $commits = $this->fetchCommits('', 30);
            foreach ($commits as $commit) {
                $stats['events'] += $this->upsertCommitEvent($project, $commit);
            }
            $stats['commits'] = count($commits);

            return ['ok' => true, 'stats' => $stats];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'stats' => $stats];
        }
    }

    // ─── Entity Upsert ─────────────────────────────────────────────────

    protected function upsertIssue(Project $project, array $ghIssue): int
    {
        $externalId = (string) $ghIssue['number'];

        $task = Task::where('project_id', $project->id)
            ->where('external_id', $externalId)
            ->first();

        $status = $ghIssue['state'] === 'closed' ? 'done' : 'todo';
        $labels = collect($ghIssue['labels'] ?? [])->pluck('name')->toArray();
        $priority = $this->detectPriority($labels);

        if ($task) {
            $task->update([
                'title' => $ghIssue['title'],
                'status' => $status,
                'priority' => $priority,
                'description' => $ghIssue['body'] ?? '',
                'completed_at' => $status === 'done' ? ($ghIssue['closed_at'] ?? now()) : null,
                'tags' => $labels,
                'meta' => array_merge($task->meta ?? [], [
                    'gh_url' => $ghIssue['html_url'] ?? '',
                    'gh_updated' => $ghIssue['updated_at'] ?? null,
                ]),
            ]);

            return 0; // updated
        }

        Task::create([
            'project_id' => $project->id,
            'external_id' => $externalId,
            'title' => $ghIssue['title'],
            'description' => $ghIssue['body'] ?? '',
            'status' => $status,
            'priority' => $priority,
            'tags' => $labels,
            'start_at' => $ghIssue['created_at'] ?? now(),
            'due_at' => $ghIssue['milestone']['due_on'] ?? null,
            'completed_at' => $status === 'done' ? ($ghIssue['closed_at'] ?? null) : null,
            'meta' => [
                'source' => 'github',
                'gh_url' => $ghIssue['html_url'] ?? '',
                'gh_user' => $ghIssue['user']['login'] ?? '',
                'gh_assignee' => collect($ghIssue['assignees'] ?? [])->pluck('login')->toArray(),
            ],
        ]);

        return 1; // created
    }

    protected function upsertPullRequest(Project $project, array $pr): int
    {
        $externalId = (string) $pr['number'];

        $task = Task::where('project_id', $project->id)
            ->where('external_id', "PR-{$externalId}")
            ->first();

        $state = $pr['state'] ?? 'open';
        $status = match ($state) {
            'closed' => isset($pr['merged_at']) ? 'done' : 'todo',
            'merged' => 'done',
            default => 'in_progress',
        };

        $labels = collect($pr['labels'] ?? [])->pluck('name')->toArray();

        $data = [
            'project_id' => $project->id,
            'external_id' => "PR-{$externalId}",
            'title' => "[PR] {$pr['title']}",
            'description' => $pr['body'] ?? '',
            'status' => $status,
            'priority' => $this->detectPriority($labels),
            'tags' => array_merge($labels, ['pr', "branch:{$pr['head']['ref']}"]),
            'start_at' => $pr['created_at'] ?? now(),
            'completed_at' => $status === 'done' ? ($pr['merged_at'] ?? $pr['closed_at'] ?? null) : null,
            'meta' => [
                'source' => 'github',
                'gh_url' => $pr['html_url'] ?? '',
                'gh_user' => $pr['user']['login'] ?? '',
                'gh_branch' => $pr['head']['ref'] ?? '',
                'gh_base' => $pr['base']['ref'] ?? '',
                'gh_merged' => isset($pr['merged_at']),
                'gh_additions' => $pr['additions'] ?? 0,
                'gh_deletions' => $pr['deletions'] ?? 0,
            ],
        ];

        if ($task) {
            $task->update($data);

            return 0;
        }

        Task::create($data);

        return 1;
    }

    protected function upsertCommitEvent(Project $project, array $commit): int
    {
        $sha = $commit['sha'] ?? '';
        $fingerprint = 'github-commit-'.$sha;

        $exists = Event::where('fingerprint', $fingerprint)->exists();
        if ($exists) {
            return 0;
        }

        Event::create([
            'project_id' => $project->id,
            'source' => 'github',
            'external_id' => $sha,
            'event_type' => 'commit',
            'fingerprint' => $fingerprint,
            'occurred_at' => $commit['commit']['author']['date'] ?? now(),
            'processed_at' => now(),
            'payload' => [
                'sha' => $sha,
                'message' => $commit['commit']['message'] ?? '',
                'author' => $commit['commit']['author']['name'] ?? '',
                'author_email' => $commit['commit']['author']['email'] ?? '',
                'url' => $commit['html_url'] ?? '',
                'files_changed' => $commit['files'] ?? [],
            ],
        ]);

        return 1;
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    protected function client(): PendingRequest
    {
        $client = Http::timeout(15)->withUserAgent('project-monitor/1.0');
        if ($this->token) {
            $client = $client->withToken($this->token);
        }

        return $client;
    }

    protected function requireApiBase(): string
    {
        if (! $this->apiBase) {
            throw new \RuntimeException('缺少 GitHub org/repo 配置。请在集成设置中填写组织名和仓库名。');
        }

        return $this->apiBase;
    }

    protected function getPaginated(string $url, array $params, int $maxPages = 10): array
    {
        $allData = [];
        $pageCount = 0;

        do {
            $resp = $this->client()->get($url, $params);

            if (! $resp->successful()) {
                throw new \RuntimeException("GitHub API error: HTTP {$resp->status()} — {$resp->body()}");
            }

            $data = $resp->json() ?? [];
            $allData = array_merge($allData, $data);
            $pageCount++;

            $nextUrl = $this->parseNextLink($resp->header('Link'));
            if ($nextUrl === null) {
                break;
            }

            // Subsequent URLs already contain all query parameters from GitHub's Link header
            $url = $nextUrl;
            $params = [];
        } while ($pageCount < $maxPages);

        return $allData;
    }

    /**
     * Parse GitHub's Link header to extract the next page URL.
     *
     * GitHub Link header format:
     *   <https://api.github.com/repos/...>; rel="next", <...>; rel="last"
     */
    protected function parseNextLink(?string $linkHeader): ?string
    {
        if (empty($linkHeader)) {
            return null;
        }

        foreach (explode(',', $linkHeader) as $part) {
            if (preg_match('/<([^>]+)>;\s*rel="next"/', trim($part), $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function detectPriority(array $labels): string
    {
        $lowerLabels = array_map('strtolower', $labels);
        if (array_intersect($lowerLabels, ['critical', 'p0', 'blocker', 'emergency'])) {
            return 'critical';
        }
        if (array_intersect($lowerLabels, ['high', 'p1', 'important'])) {
            return 'high';
        }
        if (array_intersect($lowerLabels, ['low', 'p3', 'minor', 'nice-to-have'])) {
            return 'low';
        }

        return 'medium';
    }
}
