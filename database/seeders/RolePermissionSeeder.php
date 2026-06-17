<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    protected array $permissions = [
        // Dashboard
        ['name' => '查看仪表板', 'slug' => 'view_dashboard', 'group' => 'dashboard'],
        // Board
        ['name' => '查看看板', 'slug' => 'view_board', 'group' => 'board'],
        // Projects
        ['name' => '查看项目', 'slug' => 'view_projects', 'group' => 'projects'],
        ['name' => '管理项目', 'slug' => 'manage_projects', 'group' => 'projects'],
        // Resources
        ['name' => '查看资源负载', 'slug' => 'view_resources', 'group' => 'resources'],
        // Metrics
        ['name' => '查看指标', 'slug' => 'view_metrics', 'group' => 'metrics'],
        ['name' => '管理指标', 'slug' => 'manage_metrics', 'group' => 'metrics'],
        // Reports
        ['name' => '查看报表', 'slug' => 'view_reports', 'group' => 'reports'],
        // Integrations
        ['name' => '管理集成', 'slug' => 'manage_integrations', 'group' => 'integrations'],
        // Settings
        ['name' => '管理系统设置', 'slug' => 'manage_settings', 'group' => 'settings'],
        // Roles
        ['name' => '管理角色权限', 'slug' => 'manage_roles', 'group' => 'roles'],
        // Risks
        ['name' => '查看风险中心', 'slug' => 'view_risks', 'group' => 'risks'],
        ['name' => '管理风险', 'slug' => 'manage_risks', 'group' => 'risks'],
        // Monitoring
        ['name' => '查看慢查询监控', 'slug' => 'view_monitoring', 'group' => 'monitoring'],
    ];

    protected array $roles = [
        'admin' => [
            'name'        => '管理员',
            'description' => '拥有系统所有权限',
            'permissions'  => '*', // all
        ],
        'manager' => [
            'name'        => '项目经理',
            'description' => '可查看所有数据并管理项目、指标和集成',
            'permissions'  => [
                'view_dashboard', 'view_projects', 'manage_projects',
                'view_board', 'view_resources', 'view_metrics', 'manage_metrics',
                'view_reports', 'manage_integrations', 'view_risks', 'manage_risks',
                'view_monitoring',
            ],
        ],
        'viewer' => [
            'name'        => '查看者',
            'description' => '只读查看仪表板、项目、资源和报表',
            'permissions'  => [
                'view_dashboard', 'view_projects', 'view_board',
                'view_resources', 'view_metrics', 'view_reports',
                'view_risks', 'view_monitoring',
            ],
        ],
    ];

    public function run(): void
    {
        // Create permissions
        foreach ($this->permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // Create roles and assign permissions
        foreach ($this->roles as $slug => $config) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $config['name'],
                    'description' => $config['description'],
                    'is_system'   => true,
                ]
            );

            if ($config['permissions'] === '*') {
                $role->permissions()->sync(Permission::pluck('id'));
            } else {
                $ids = Permission::whereIn('slug', $config['permissions'])->pluck('id');
                $role->permissions()->sync($ids);
            }
        }

        // Assign admin role to first user (demo user)
        $admin = User::first();
        if ($admin && ! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command?->info('角色权限数据已初始化。');
    }
}
