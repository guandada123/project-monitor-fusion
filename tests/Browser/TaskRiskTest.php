<?php

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Task Board ───────────────

test('task board page loads', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/board')
            ->assertPathIs('/board')
            ->assertSee('看板');
    });
});

test('task board shows projects and tasks', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => '看板项目',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'title' => '看板任务一',
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/board')
            ->assertPathIs('/board')
            ->assertSee('看板');
    });
});

// ─────────────── Risk Manager ───────────────

test('risk manager page loads', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/risks')
            ->assertPathIs('/risks')
            ->assertSee('风险中心');
    });
});

test('risk manager shows existing risks', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => '风险项目',
        ]);

        Risk::factory()->create([
            'project_id' => $project->id,
            'title' => '高风险问题',
            'severity' => 'high',
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/risks')
            ->assertPathIs('/risks')
            ->assertSee('风险中心');
    });
});

// ─────────────── Other Pages ───────────────

test('metrics builder page loads', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/metrics')
            ->assertPathIs('/metrics')
            ->assertSee('指标');
    });
});

test('report center page loads', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/reports')
            ->assertPathIs('/reports')
            ->assertSee('报表');
    });
});

test('settings page loads', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->visit('/settings')
            ->assertPathIs('/settings')
            ->assertSee('设置');
    });
});
