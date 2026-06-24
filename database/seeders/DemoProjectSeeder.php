<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoProjectSeeder extends Seeder
{
    /**
     * 3 个演示项目 + 20 个任务 + 5 个风险 + 多用户 + 指标 + 设置
     * 运行时间预期 < 5s
     */
    public function run(): void
    {
        // ─── 1. 创建演示用户 ───
        $demoAdmin = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => '演示管理员',
                'password' => bcrypt('demo1234'),
                'email_verified_at' => now(),
            ]
        );
        if (! $demoAdmin->hasRole('admin')) {
            $demoAdmin->assignRole('admin');
        }

        $manager1 = User::firstOrCreate(
            ['email' => 'zhangsan@demo.com'],
            [
                'name' => '张三（项目经理）',
                'password' => bcrypt('demo1234'),
                'email_verified_at' => now(),
            ]
        );
        if (! $manager1->hasRole('manager')) {
            $manager1->assignRole('manager');
        }

        $manager2 = User::firstOrCreate(
            ['email' => 'lisi@demo.com'],
            [
                'name' => '李四（研发经理）',
                'password' => bcrypt('demo1234'),
                'email_verified_at' => now(),
            ]
        );
        if (! $manager2->hasRole('manager')) {
            $manager2->assignRole('manager');
        }

        $viewer = User::firstOrCreate(
            ['email' => 'wangwu@demo.com'],
            [
                'name' => '王五（观察者）',
                'password' => bcrypt('demo1234'),
                'email_verified_at' => now(),
            ]
        );
        if (! $viewer->hasRole('viewer')) {
            $viewer->assignRole('viewer');
        }

        $users = [$demoAdmin, $manager1, $manager2, $viewer];

        // ─── 2. 演示项目定义 ───

        $projectsData = [
            [
                'name' => '电商平台重构 v3.0',
                'slug' => 'ecommerce-v3',
                'source' => 'manual',
                'status' => 'active',
                'description' => '基于微服务架构重构现有电商平台，包括订单系统、支付网关、商品服务三个核心模块的重写。目标是将单体应用拆分为 6 个独立服务。',
                'start_at' => now()->subDays(30),
                'end_at' => now()->addDays(60),
                'owner_id' => $manager1->id,
                'meta' => ['priority' => 'high', 'team_size' => 8, 'stack' => 'Laravel+Vue+PostgreSQL'],
                'tags' => ['重构', '微服务', '电商'],
                'config' => ['github_repo' => 'org/ecommerce-v3', 'slack_channel' => '#proj-ecom'],
            ],
            [
                'name' => '内部数据分析平台',
                'slug' => 'analytics-platform',
                'source' => 'github',
                'status' => 'active',
                'description' => '为公司搭建统一的数据分析平台，支持实时 BI 报表、自定义看板和自动化报告推送。对接销售、运营、财务三个数据源。',
                'start_at' => now()->subDays(14),
                'end_at' => now()->addDays(90),
                'owner_id' => $manager2->id,
                'meta' => ['priority' => 'medium', 'team_size' => 5, 'stack' => 'Python+FastAPI+React'],
                'tags' => ['数据分析', 'BI', '看板'],
                'config' => ['github_repo' => 'org/analytics-platform'],
            ],
            [
                'name' => '运维监控告警系统',
                'slug' => 'ops-monitor',
                'source' => 'manual',
                'status' => 'planning',
                'description' => '统一监控告警平台，聚合服务器、容器、数据库、网络设备的监控指标，支持多级告警策略和飞书/钉钉/邮件多渠道推送。',
                'start_at' => now()->addDays(7),
                'end_at' => now()->addDays(120),
                'owner_id' => $demoAdmin->id,
                'meta' => ['priority' => 'low', 'team_size' => 3, 'stack' => 'Go+Prometheus+Grafana'],
                'tags' => ['运维', '监控', '告警'],
                'config' => ['slack_channel' => '#proj-ops'],
            ],
        ];

        $projects = [];
        foreach ($projectsData as $data) {
            $projects[] = Project::firstOrCreate(['slug' => $data['slug']], $data);
        }

        // ─── 3. 任务 ───

        // 电商项目 — 8 个任务
        $ecomTasks = [
            ['title' => '用户模块微服务设计', 'status' => 'done',     'priority' => 'critical', 'estimate' => 5.0, 'spent' => 4.5, 'assignee_id' => $manager1->id, 'start_at' => now()->subDays(28), 'due_at' => now()->subDays(21), 'completed_at' => now()->subDays(22), 'tags' => ['架构']],
            ['title' => '订单服务数据库分表方案', 'status' => 'done',   'priority' => 'high',     'estimate' => 3.0, 'spent' => 3.5, 'assignee_id' => $manager1->id, 'start_at' => now()->subDays(25), 'due_at' => now()->subDays(18), 'completed_at' => now()->subDays(19), 'tags' => ['数据库']],
            ['title' => '支付网关 API 对接', 'status' => 'in_progress', 'priority' => 'critical', 'estimate' => 8.0, 'spent' => 6.0, 'assignee_id' => $manager2->id, 'start_at' => now()->subDays(20), 'due_at' => now()->addDays(5),  'tags' => ['支付', 'API']],
            ['title' => '商品搜索引擎集成（Elasticsearch）', 'status' => 'in_progress', 'priority' => 'high', 'estimate' => 6.0, 'spent' => 2.0, 'assignee_id' => $manager1->id, 'start_at' => now()->subDays(14), 'due_at' => now()->addDays(10), 'tags' => ['搜索']],
            ['title' => 'API 网关统一鉴权', 'status' => 'todo',       'priority' => 'high',     'estimate' => 4.0, 'spent' => 0,   'assignee_id' => $manager2->id, 'start_at' => now()->addDays(3),  'due_at' => now()->addDays(14),  'tags' => ['安全']],
            ['title' => '压力测试与性能优化', 'status' => 'todo',       'priority' => 'medium',   'estimate' => 5.0, 'spent' => 0,   'assignee_id' => null,            'start_at' => now()->addDays(14), 'due_at' => now()->addDays(28),  'tags' => ['测试']],
            ['title' => 'Docker Compose 本地开发环境', 'status' => 'done', 'priority' => 'medium', 'estimate' => 2.0, 'spent' => 2.0, 'assignee_id' => $manager1->id, 'start_at' => now()->subDays(30), 'due_at' => now()->subDays(26), 'completed_at' => now()->subDays(27), 'tags' => ['DevOps']],
            ['title' => '部署文档与运维手册', 'status' => 'todo',       'priority' => 'low',      'estimate' => 3.0, 'spent' => 0,   'assignee_id' => null,            'start_at' => now()->addDays(30), 'due_at' => now()->addDays(50),  'tags' => ['文档']],
        ];

        // 分析平台 — 7 个任务
        $analyticsTasks = [
            ['title' => '销售数据 ETL 管道搭建', 'status' => 'in_progress', 'priority' => 'high',   'estimate' => 6.0, 'spent' => 4.0, 'assignee_id' => $manager2->id, 'start_at' => now()->subDays(12), 'due_at' => now()->addDays(8),  'tags' => ['ETL']],
            ['title' => 'BI 看板前端框架选型', 'status' => 'done',         'priority' => 'high',   'estimate' => 2.0, 'spent' => 1.5, 'assignee_id' => $manager1->id, 'start_at' => now()->subDays(14), 'due_at' => now()->subDays(10), 'completed_at' => now()->subDays(11), 'tags' => ['前端']],
            ['title' => '运营数据源 API 对接', 'status' => 'in_progress', 'priority' => 'medium', 'estimate' => 4.0, 'spent' => 1.0, 'assignee_id' => $manager2->id, 'start_at' => now()->subDays(7),  'due_at' => now()->addDays(14),  'tags' => ['API']],
            ['title' => '自动化报告订阅推送', 'status' => 'todo',          'priority' => 'medium', 'estimate' => 3.0, 'spent' => 0,   'assignee_id' => null,            'start_at' => now()->addDays(10), 'due_at' => now()->addDays(30),  'tags' => ['自动化']],
            ['title' => '财务数据权限模型设计', 'status' => 'todo',        'priority' => 'high',   'estimate' => 3.0, 'spent' => 0,   'assignee_id' => $manager1->id, 'start_at' => now()->addDays(5),  'due_at' => now()->addDays(18),  'tags' => ['安全']],
            ['title' => '看板移动端适配', 'status' => 'todo',              'priority' => 'low',    'estimate' => 5.0, 'spent' => 0,   'assignee_id' => null,            'start_at' => now()->addDays(40), 'due_at' => now()->addDays(70),  'tags' => ['移动端']],
            ['title' => '数据质量监控看板', 'status' => 'todo',            'priority' => 'low',    'estimate' => 2.0, 'spent' => 0,   'assignee_id' => null,            'start_at' => now()->addDays(60), 'due_at' => now()->addDays(85),  'tags' => ['监控']],
        ];

        // 运维监控 — 5 个任务
        $opsTasks = [
            ['title' => '监控指标采集 Agent 开发', 'status' => 'todo',       'priority' => 'high',   'estimate' => 8.0, 'spent' => 0, 'assignee_id' => null,            'start_at' => now()->addDays(14), 'due_at' => now()->addDays(40),  'tags' => ['开发']],
            ['title' => '告警规则引擎设计', 'status' => 'todo',              'priority' => 'high',   'estimate' => 5.0, 'spent' => 0, 'assignee_id' => null,            'start_at' => now()->addDays(20), 'due_at' => now()->addDays(45),  'tags' => ['架构']],
            ['title' => '飞书/钉钉多渠道推送适配', 'status' => 'todo',       'priority' => 'medium', 'estimate' => 3.0, 'spent' => 0, 'assignee_id' => null,            'start_at' => now()->addDays(35), 'due_at' => now()->addDays(60),  'tags' => ['集成']],
            ['title' => 'Grafana Dashboard 模版制作', 'status' => 'todo',   'priority' => 'medium', 'estimate' => 4.0, 'spent' => 0, 'assignee_id' => null,            'start_at' => now()->addDays(45), 'due_at' => now()->addDays(70),  'tags' => ['可视化']],
            ['title' => 'POC 演示环境搭建', 'status' => 'todo',              'priority' => 'critical', 'estimate' => 3.0, 'spent' => 0, 'assignee_id' => $demoAdmin->id, 'start_at' => now()->addDays(7),  'due_at' => now()->addDays(21),  'tags' => ['DevOps']],
        ];

        $taskMap = [
            $projects[0]->id => $ecomTasks,
            $projects[1]->id => $analyticsTasks,
            $projects[2]->id => $opsTasks,
        ];

        $createdTasks = [];
        foreach ($taskMap as $projectId => $tasks) {
            foreach ($tasks as $t) {
                $task = Task::create(array_merge($t, ['project_id' => $projectId]));
                $createdTasks[] = $task;
            }
        }

        // ─── 4. 分配（Assignments）───

        $assignmentRoles = ['developer', 'reviewer', 'tester'];
        foreach ($createdTasks as $task) {
            if ($task->assignee_id) {
                Assignment::create([
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                    'role' => $assignmentRoles[array_rand($assignmentRoles)],
                    'allocation_percent' => rand(30, 100),
                ]);
            }
            // 额外添加随机 reviewer
            if (rand(0, 1) && $task->assignee_id) {
                $reviewer = $users[array_rand($users)];
                if ($reviewer->id !== $task->assignee_id) {
                    Assignment::firstOrCreate([
                        'task_id' => $task->id,
                        'user_id' => $reviewer->id,
                    ], ['role' => 'reviewer', 'allocation_percent' => 20]);
                }
            }
        }

        // ─── 5. 风险 ───

        $risksData = [
            [
                'project_id' => $projects[0]->id,
                'severity' => 'critical',
                'rule_key' => 'deadline_slip',
                'title' => '支付网关对接依赖第三方审批，可能延期 2 周',
                'detail' => '微信支付和支付宝的商户号审批流程预计需要 10-15 个工作日，当前尚未提交申请。如果继续延迟，将阻塞 API 对接任务。',
                'status' => 'open',
                'occurrence_count' => 1,
                'suggestion' => '立即提交商户号申请，同时准备沙箱环境先行联调',
            ],
            [
                'project_id' => $projects[0]->id,
                'severity' => 'high',
                'rule_key' => 'resource_bottleneck',
                'title' => '订单服务数据库连接池不足',
                'detail' => '当前连接池配置最大 50 连接，预计拆分后 QPS 增长 3 倍，需提前扩容或引入连接池中间件。',
                'status' => 'open',
                'occurrence_count' => 2,
                'suggestion' => '增加连接池到 100，评估是否需要 PgBouncer 中间件',
            ],
            [
                'project_id' => $projects[0]->id,
                'severity' => 'medium',
                'rule_key' => 'tech_debt',
                'title' => '旧系统数据迁移方案未确定',
                'detail' => '电商平台现有 200 万订单记录，停服迁移预计需要 4 小时窗口，业务方尚未批复。',
                'status' => 'open',
                'occurrence_count' => 1,
                'suggestion' => '制定灰度迁移方案，先迁移 30% 流量观察 1 周',
            ],
            [
                'project_id' => $projects[1]->id,
                'severity' => 'high',
                'rule_key' => 'data_quality',
                'title' => '销售数据源存在字段定义不一致',
                'detail' => 'CRM 系统与 ERP 系统中"订单金额"字段定义不同（含税/不含税），ETL 管道需做数据清洗映射。',
                'status' => 'acknowledged',
                'occurrence_count' => 3,
                'suggestion' => '与业务方确认统一字段定义，ETL 中加入转换层',
            ],
            [
                'project_id' => $projects[2]->id,
                'severity' => 'medium',
                'rule_key' => 'scope_creep',
                'title' => '监控指标范围不断扩展，超出 MVP 边界',
                'detail' => '初始计划覆盖 30 个指标，现已扩展至 60+，影响项目排期。需冻结 MVP 范围。',
                'status' => 'open',
                'occurrence_count' => 1,
                'suggestion' => '组织范围冻结会议，MVP 后剩余指标放入 V2',
            ],
        ];

        foreach ($risksData as $r) {
            Risk::create(array_merge($r, [
                'first_seen_at' => now()->subDays(rand(5, 20)),
                'last_seen_at' => now()->subDays(rand(0, 3)),
            ]));
        }

        // ─── 6. 系统指标 ───

        $defaultMetrics = [
            ['key' => 'progress_deviation', 'name' => '进度偏差',       'kind' => 'system'],
            ['key' => 'schedule_adherence', 'name' => '工期遵从率',     'kind' => 'system'],
            ['key' => 'defect_density',     'name' => '缺陷密度',       'kind' => 'system'],
            ['key' => 'commit_frequency',   'name' => '代码提交频率',   'kind' => 'system'],
            ['key' => 'pr_merge_time',      'name' => 'PR 合并时间',    'kind' => 'system'],
            ['key' => 'ci_pass_rate',       'name' => 'CI 通过率',     'kind' => 'system'],
            ['key' => 'test_coverage',      'name' => '测试覆盖率',     'kind' => 'system'],
            ['key' => 'team_load_rate',     'name' => '团队负载率',     'kind' => 'system'],
        ];

        foreach ($projects as $project) {
            foreach ($defaultMetrics as $m) {
                $prefixedKey = $project->slug.'-'.$m['key'];
                $metric = Metric::firstOrCreate([
                    'key' => $prefixedKey,
                ], array_merge($m, [
                    'project_id' => $project->id,
                    'key' => $prefixedKey,
                    'current_value' => round(mt_rand(50, 100) + mt_rand(0, 99) / 100, 2),
                    'last_computed_at' => now()->subHours(rand(1, 12)),
                ]));

                // 7 天历史快照
                for ($d = 7; $d >= 0; $d--) {
                    MetricSnapshot::create([
                        'metric_id' => $metric->id,
                        'value' => round(mt_rand(40, 100) + mt_rand(0, 99) / 100, 2),
                        'captured_at' => now()->subDays($d)->setTime(rand(8, 18), rand(0, 59)),
                    ]);
                }
            }
        }

        // ─── 7. 系统设置 ───

        $defaultSettings = [
            ['key' => 'app_name',       'value' => 'Project Monitor',               'group' => 'general'],
            ['key' => 'timezone',       'value' => 'Asia/Shanghai',                 'group' => 'general'],
            ['key' => 'daily_report_time', 'value' => '08:30',                       'group' => 'notification'],
            ['key' => 'feishu_webhook', 'value' => 'https://open.feishu.cn/...',     'group' => 'notification'],
            ['key' => 'slow_query_threshold_ms', 'value' => '500',                  'group' => 'monitoring'],
            ['key' => 'retention_days', 'value' => '90',                            'group' => 'monitoring'],
        ];

        foreach ($defaultSettings as $s) {
            Setting::firstOrCreate(['key' => $s['key']], $s);
        }

        $this->command?->info(sprintf(
            '✅ 演示数据已生成：%d 用户 / %d 项目 / %d 任务 / %d 风险 / %d 指标 / %d 设置',
            count($users), count($projects), count($createdTasks), count($risksData),
            count($defaultMetrics) * count($projects), count($defaultSettings)
        ));
    }
}
