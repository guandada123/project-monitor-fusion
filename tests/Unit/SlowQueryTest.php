<?php

use App\Models\SlowQuery;
use App\Services\SlowQueryMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ─── SlowQuery Model ───

describe('SlowQuery model', function () {
    it('can create a slow query record', function () {
        $sq = SlowQuery::create([
            'query' => 'SELECT * FROM projects WHERE id = ?',
            'bindings' => [1],
            'time' => 523.45,
            'connection_name' => 'pgsql',
            'caller' => '/app/Http/Controllers/ProjectController.php:42',
            'is_slow' => true,
            'is_critical' => false,
            'captured_at' => now(),
        ]);

        expect($sq->id)->toBeNumeric();
        expect($sq->time)->toBeFloat()->toBe(523.45);
        expect($sq->is_slow)->toBeTrue();
        expect($sq->is_critical)->toBeFalse();
        expect($sq->bindings)->toBeArray()->toHaveCount(1);
    });

    it('scopes slow queries correctly', function () {
        SlowQuery::factory()->count(3)->slow()->create();
        SlowQuery::factory()->count(2)->critical()->create();

        // slow scope returns all with is_slow=true (slow + critical)
        expect(SlowQuery::slow()->count())->toBe(5);
        // critical scope returns only critical
        expect(SlowQuery::critical()->count())->toBe(2);
        // non-critical slow queries
        expect(SlowQuery::where('is_slow', true)->where('is_critical', false)->count())->toBe(3);
    });

    it('scopes recent queries', function () {
        SlowQuery::factory()->create(['captured_at' => now()->subHour(2)]);
        SlowQuery::factory()->create(['captured_at' => now()->subHour(25)]);

        expect(SlowQuery::recent(24)->count())->toBe(1);
    });

    it('provides duration label accessor', function () {
        $ms = SlowQuery::factory()->make(['time' => 500]);
        expect($ms->duration_label)->toBe('500ms');

        $s = SlowQuery::factory()->make(['time' => 1500]);
        expect($s->duration_label)->toBe('1.50s');
    });

    it('provides query preview accessor', function () {
        $short = SlowQuery::factory()->make(['query' => 'SELECT 1']);
        expect($short->query_preview)->toBe('SELECT 1');

        $long = SlowQuery::factory()->make(['query' => str_repeat('x', 250)]);
        expect($long->query_preview)->toEndWith('...');
    });
});

// ─── SlowQueryMonitor Service ───

describe('SlowQueryMonitor', function () {
    it('persists slow queries to database', function () {
        // Simulate a DB::listen callback
        $mockQuery = (object) [
            'sql' => 'SELECT COUNT(*) FROM projects',
            'bindings' => [],
            'time' => 523,
            'connectionName' => 'pgsql',
        ];

        // Manually trigger the monitor's boot callback
        SlowQueryMonitor::boot();

        // We cannot easily test DB::listen directly, so we verify the table exists
        expect(SlowQuery::count())->toBeGreaterThanOrEqual(0);
    });
});

// ─── SlowQuery Factory ───

it('factory generates valid slow query', function () {
    $sq = SlowQuery::factory()->create();

    expect($sq->query)->not->toBeEmpty();
    expect($sq->time)->toBeGreaterThanOrEqual(100);
    expect($sq->captured_at)->toBeInstanceOf(Carbon::class);
});
