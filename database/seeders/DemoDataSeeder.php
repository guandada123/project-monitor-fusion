<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Integration;
use App\Models\Metric;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        $admin = User::factory()->create(['name' => '关', 'email' => 'guan@pm.local']);
        $users = [
            $admin,
            User::factory()->create(['name' => '张工', 'email' => 'zhang@pm.local']),
            User::factory()->create(['name' => '李前端', 'email' => 'li@pm.local']),
            User::factory()->create(['name' => '王后端', 'email' => 'wang@pm.local']),
            User::factory()->create(['name' => '赵测试', 'email' => 'zhao@pm.local']),
        ];

        // Projects
        $projects = [
            Project::create([
                'name' => 'Project Monitor MVP',
                'slug' => 'pm-mvp',
                'source' => 'github',
                'owner_id' => $admin->id,
                'status' => 'active',
                'description' => '项目监控系统 MVP 版本 — 核心功能：仪表板、风险预警、KPI、报表',
                'start_at' => '2026-05-20',
                'end_at' => '2026-07-15',
                'tags' => ['mvp', 'dashboard', 'backend'],
            ]),
            Project::create([
                'name' => '微信支付集成',
                'slug' => 'wechat-pay',
                'source' => 'jira',
                'owner_id' => $users[1]->id,
                'status' => 'active',
                'description' => '微信支付 SDK 集成，含 H5/JSAPI/小程序全场景覆盖',
                'start_at' => '2026-06-01',
                'end_at' => '2026-08-01',
                'tags' => ['payment', 'integration'],
            ]),
            Project::create([
                'name' => '用户中心 2.0',
                'slug' => 'user-center-v2',
                'source' => 'github',
                'owner_id' => $users[1]->id,
                'status' => 'active',
                'description' => '用户中心重构，SSO 统一登录，权限体系升级',
                'start_at' => '2026-04-15',
                'end_at' => '2026-06-30',
                'tags' => ['auth', 'sso', 'refactor'],
            ]),
            Project::create([
                'name' => '数据分析平台',
                'slug' => 'data-analytics',
                'source' => 'manual',
                'owner_id' => $admin->id,
                'status' => 'on_hold',
                'description' => '内部 BI 分析平台，目前人力资源紧张暂停',
                'start_at' => '2026-03-01',
                'end_at' => '2026-09-01',
                'tags' => ['bi', 'analytics', 'big-data'],
            ]),
        ];

        // Tasks for each project
        $taskData = [
            'pm-mvp' => [
                ['title' => '数据库设计与迁移', 'status' => 'done', 'priority' => 'high', 'estimate' => 16, 'spent' => 14, 'due_at' => '2026-05-25', 'completed_at' => '2026-05-24', 'assignee' => 2],
                ['title' => '仪表板 Livewire 组件', 'status' => 'in_progress', 'priority' => 'high', 'estimate' => 24, 'spent' => 10, 'due_at' => '2026-06-05', 'assignee' => 1],
                ['title' => '主题切换系统', 'status' => 'done', 'priority' => 'high', 'estimate' => 8, 'spent' => 10, 'due_at' => '2026-05-28', 'completed_at' => '2026-05-27', 'assignee' => 2],
                ['title' => '风险引擎核心逻辑', 'status' => 'todo', 'priority' => 'high', 'estimate' => 20, 'spent' => 0, 'due_at' => '2026-06-10', 'assignee' => 3],
                ['title' => 'GitHub Webhook 集成', 'status' => 'in_progress', 'priority' => 'medium', 'estimate' => 16, 'spent' => 5, 'due_at' => '2026-06-12', 'assignee' => 2],
                ['title' => 'ApexCharts 图表集成', 'status' => 'todo', 'priority' => 'medium', 'estimate' => 12, 'spent' => 0, 'due_at' => '2026-06-15', 'assignee' => 1],
                ['title' => '飞书推送日报', 'status' => 'todo', 'priority' => 'medium', 'estimate' => 10, 'spent' => 0, 'due_at' => '2026-06-20', 'assignee' => 3],
                ['title' => '性能优化 - 首屏 < 1.5s', 'status' => 'todo', 'priority' => 'low', 'estimate' => 8, 'spent' => 0, 'due_at' => '2026-07-01', 'assignee' => 1],
            ],
            'wechat-pay' => [
                ['title' => 'H5 支付接入', 'status' => 'done', 'priority' => 'high', 'estimate' => 20, 'spent' => 22, 'due_at' => '2026-06-10', 'completed_at' => '2026-06-09', 'assignee' => 3],
                ['title' => 'JSAPI 支付开发', 'status' => 'in_progress', 'priority' => 'high', 'estimate' => 24, 'spent' => 15, 'due_at' => '2026-06-25', 'assignee' => 3],
                ['title' => '订单查询接口', 'status' => 'todo', 'priority' => 'medium', 'estimate' => 12, 'spent' => 0, 'due_at' => '2026-07-05', 'assignee' => 1],
                ['title' => '退款流程开发', 'status' => 'todo', 'priority' => 'medium', 'estimate' => 16, 'spent' => 0, 'due_at' => '2026-07-15', 'assignee' => 3],
                ['title' => '支付安全审计', 'status' => 'blocked', 'priority' => 'critical', 'estimate' => 8, 'spent' => 2, 'due_at' => '2026-06-20', 'assignee' => 4],
            ],
            'user-center-v2' => [
                ['title' => 'SSO 单点登录模块', 'status' => 'done', 'priority' => 'high', 'estimate' => 30, 'spent' => 28, 'due_at' => '2026-05-01', 'completed_at' => '2026-04-28', 'assignee' => 3],
                ['title' => 'RBAC 权限设计', 'status' => 'done', 'priority' => 'high', 'estimate' => 20, 'spent' => 18, 'due_at' => '2026-05-15', 'completed_at' => '2026-05-14', 'assignee' => 1],
                ['title' => '用户导入迁移脚本', 'status' => 'in_progress', 'priority' => 'high', 'estimate' => 12, 'spent' => 8, 'due_at' => '2026-06-10', 'assignee' => 3],
                ['title' => '前端登录页重构', 'status' => 'in_progress', 'priority' => 'medium', 'estimate' => 16, 'spent' => 6, 'due_at' => '2026-06-18', 'assignee' => 2],
                ['title' => 'OAuth2 第三方登录', 'status' => 'todo', 'priority' => 'low', 'estimate' => 20, 'spent' => 0, 'due_at' => '2026-06-30', 'assignee' => 3],
                ['title' => '安全漏洞修复 - XSS', 'status' => 'todo', 'priority' => 'critical', 'estimate' => 4, 'spent' => 0, 'due_at' => '2026-06-05', 'assignee' => 4],
            ],
            'data-analytics' => [
                ['title' => '数据源接入层', 'status' => 'done', 'priority' => 'high', 'estimate' => 20, 'spent' => 18, 'due_at' => '2026-04-01', 'completed_at' => '2026-03-28', 'assignee' => 3],
                ['title' => '可视化看板', 'status' => 'todo', 'priority' => 'high', 'estimate' => 24, 'spent' => 0, 'due_at' => '2026-09-01', 'assignee' => 2],
            ],
        ];

        foreach ($taskData as $slug => $tasks) {
            $project = Project::where('slug', $slug)->first();
            foreach ($tasks as $td) {
                Task::create([
                    'project_id' => $project->id,
                    'external_id' => strtoupper(Str::random(8)),
                    'title' => $td['title'],
                    'status' => $td['status'],
                    'priority' => $td['priority'],
                    'estimate' => $td['estimate'],
                    'spent' => $td['spent'],
                    'start_at' => $project->start_at,
                    'due_at' => $td['due_at'],
                    'completed_at' => $td['completed_at'] ?? null,
                    'assignee_id' => $users[$td['assignee']]->id ?? null,
                ]);
            }
        }

        // Risks
        $risks = [
            ['project_slug' => 'pm-mvp', 'severity' => 'high', 'rule_key' => 'overdue_tasks_ratio', 'title' => '仪表板组件开发进度滞后', 'detail' => '预估 24h，当前已达 10h 且还在进行中，延期风险高', 'status' => 'open'],
            ['project_slug' => 'pm-mvp', 'severity' => 'medium', 'rule_key' => 'resource_bottleneck', 'title' => '前端资源紧张', 'detail' => '李前端的 workload 已达 120%，可能影响排期', 'status' => 'open'],
            ['project_slug' => 'wechat-pay', 'severity' => 'critical', 'rule_key' => 'task_blocked', 'title' => '支付安全审计阻塞', 'detail' => '安全审计任务 blocked，需赵测试优先处理', 'status' => 'open'],
            ['project_slug' => 'user-center-v2', 'severity' => 'critical', 'rule_key' => 'security_vulnerability', 'title' => 'XSS 安全漏洞未修复', 'detail' => '严重安全漏洞 overdue，需立即处理', 'status' => 'open'],
            ['project_slug' => 'user-center-v2', 'severity' => 'low', 'rule_key' => 'progress_deviation', 'title' => 'OAuth2 模块进度偏慢', 'detail' => '原计划 6 月初启动，目前仍为 todo', 'status' => 'acknowledged'],
            ['project_slug' => 'data-analytics', 'severity' => 'medium', 'rule_key' => 'project_stalled', 'title' => '数据分析平台长期挂起', 'detail' => '项目已 on_hold 超过 2 个月，缺少重启计划', 'status' => 'open'],
        ];

        foreach ($risks as $rd) {
            $project = Project::where('slug', $rd['project_slug'])->first();
            Risk::create([
                'project_id' => $project->id,
                'severity' => $rd['severity'],
                'rule_key' => $rd['rule_key'],
                'title' => $rd['title'],
                'detail' => $rd['detail'],
                'status' => $rd['status'],
                'first_seen_at' => now()->subDays(rand(1, 14)),
                'last_seen_at' => now(),
                'occurrence_count' => rand(1, 5),
            ]);
        }

        // Metrics - 8 default system KPIs
        $defaultMetrics = [
            ['key' => 'completion_rate', 'name' => '整体完成率', 'kind' => 'system'],
            ['key' => 'overdue_ratio', 'name' => '延期率', 'kind' => 'system'],
            ['key' => 'burndown_deviation', 'name' => '燃尽偏差', 'kind' => 'system'],
            ['key' => 'avg_workload', 'name' => '人均负载', 'kind' => 'system'],
            ['key' => 'wip_count', 'name' => 'WIP 在制品', 'kind' => 'system'],
            ['key' => 'avg_lead_time', 'name' => '平均周期 LeadTime', 'kind' => 'system'],
            ['key' => 'defect_rate', 'name' => '缺陷率', 'kind' => 'system'],
            ['key' => 'unclosed_risks', 'name' => '风险未闭环数', 'kind' => 'system'],
        ];

        // Create global metrics and per-project metrics
        foreach ($defaultMetrics as $md) {
            Metric::create([
                'project_id' => null,
                'key' => $md['key'],
                'name' => $md['name'],
                'kind' => $md['kind'],
            ]);
        }

        foreach ($projects as $project) {
            foreach ($defaultMetrics as $md) {
                Metric::create([
                    'project_id' => $project->id,
                    'key' => "{$project->slug}-{$md['key']}",
                    'name' => $md['name'],
                    'kind' => $md['kind'],
                    'current_value' => match ($md['key']) {
                        'completion_rate' => $project->completion_rate,
                        'overdue_ratio' => $project->overdue_rate,
                        'unclosed_risks' => $project->open_risk_count,
                        default => rand(10, 90),
                    },
                ]);
            }
        }

        // Integration placeholder
        Integration::create([
            'type' => 'github',
            'name' => 'Livewire (公开仓库)',
            'config' => ['org' => 'livewire', 'repo' => 'livewire', 'token' => ''],
            'status' => 'active',
        ]);

        Integration::create([
            'type' => 'feishu',
            'name' => '飞书群推送',
            'config' => ['chat_id' => 'oc_9ee5303497f5e0e71666b610d6bdc346'],
            'status' => 'active',
        ]);
    }
}
