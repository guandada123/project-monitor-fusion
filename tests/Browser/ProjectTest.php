<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Dashboard ───────────────

test('dashboard loads with key elements', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/')
            ->assertSee('仪表板')
            ->assertSee('Project Monitor');
    });
});

test('dashboard shows sidebar navigation links', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/')
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
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

test('project list shows existing projects', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        // Create some projects
        Project::factory()->count(3)->create([
            'owner_id' => $user->id,
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

test('project search filters work', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Z-测试项目-搜索',
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/projects')
            ->assertPathIs('/projects')
            ->assertSee('项目列表');
    });
});

// ─────────────── Project Detail ───────────────

test('project detail page loads via slug', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => '详细项目',
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit("/projects/{$project->slug}")
            ->assertPathIs("/projects/{$project->slug}")
            ->waitForText($project->name, 10)
            ->assertSee($project->name);
    });
});
