<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Str;

/**
 * 自动发现新项目：GitHub name-prefix / topic 识别 + 模板映射
 */
class ProjectDiscoveryService
{
    /**
     * 处理 GitHub 事件载荷，识别是否为"受管项目"
     * 规则：
     *   - 仓库名以 "project:" 开头
     *   - 或者 topics 包含 "managed-project"
     */
    public static function discoverFromGitHub(array $payload, string $source = 'github'): ?Project
    {
        $repo = $payload['repository'] ?? [];
        $name = $repo['name'] ?? '';
        $topics = $repo['topics'] ?? [];

        $isManaged = Str::startsWith($name, 'project:')
                  || in_array('managed-project', $topics);

        if (! $isManaged) {
            return null;
        }

        $slug = Str::slug(str_replace('project:', '', $name));
        if (Project::where('slug', $slug)->exists()) {
            return null;
        } // 已存在

        return Project::create([
            'name' => $name,
            'slug' => $slug,
            'source' => $source,
            'external_id' => (string) ($repo['id'] ?? ''),
            'status' => 'active',
            'start_at' => now(),
            'meta' => [
                'description' => $repo['description'] ?? '',
                'html_url' => $repo['html_url'] ?? '',
                'language' => $repo['language'] ?? '',
            ],
        ]);
    }
}
