<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Dashboard ───────────────

test('dashboard loads with key elements', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'dashboard@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
            ->assertPathIs('/')
            ->assertSee('仪表板')
            ->assertSee('Project Monitor');
    });
});

test('dashboard shows sidebar navigation links', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'sidebar@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
            ->assertSeeLink('仪表板')
            ->assertSeeLink('项目列表')
            ->assertSeeLink('看板')
            ->assertSeeLink('风险中心')
            ->assertSeeLink('报表中心');
    });
});

// ─────────────── Project List ───────────────

test('project list page loads with no projects', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'projects@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        $browser->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

test('project list shows existing projects', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'projects2@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create some projects
        Project::factory()->count(3)->create([
            'owner_id' => $user->id,
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        $browser->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

test('project search filters work', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'searchp@example.com',
            'password' => bcrypt('password'),
        ]);

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Z-测试项目-搜索',
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        $browser->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

// ─────────────── Project Detail ───────────────

test('project detail page loads via slug', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'detail@example.com',
            'password' => bcrypt('password'),
        ]);

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => '详细项目',
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        $browser->visit("/projects/{$project->slug}")
            ->assertPathIs("/projects/{$project->slug}")
            ->assertSee($project->name);
    });
});
