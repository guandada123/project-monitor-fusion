<?php

use Illuminate\Support\Facades\Artisan;

// ─── 2026-11-05：导入端陈旧阈值与日报统一到 ops.ci_stale_hours ───
// 此前 signature 写死 6h、日报是 2h：同一份 3 小时前的快照，
// 导入端说「不陈旧」、日报说「已陈旧」——双口径互相打架。

function writeTempOpsSnapshot(string $generatedAt): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'ops_snap_');
    file_put_contents($path, json_encode([
        'generated_at' => $generatedAt,
        'projects' => ['items' => []],
    ]));

    return $path;
}

test('import uses shared ci_stale_hours threshold by default', function () {
    config(['ops.ci_stale_hours' => 2]);
    $path = writeTempOpsSnapshot(now()->subHours(3)->toIso8601String());
    config(['ops.paths.ops_snapshot' => $path]);

    try {
        $exit = Artisan::call('ops:import-snapshot', ['--dry-run' => true]);
        $out = Artisan::output();

        expect($exit)->toBe(0)
            ->and($out)->toContain('快照已陈旧')
            ->and($out)->toContain('阈值 2h');
    } finally {
        @unlink($path);
    }
});

test('explicit stale-hours option still overrides the shared default', function () {
    config(['ops.ci_stale_hours' => 2]);
    $path = writeTempOpsSnapshot(now()->subHours(3)->toIso8601String());
    config(['ops.paths.ops_snapshot' => $path]);

    try {
        $exit = Artisan::call('ops:import-snapshot', ['--dry-run' => true, '--stale-hours' => 9]);

        expect($exit)->toBe(0)
            ->and(Artisan::output())->not->toContain('快照已陈旧');
    } finally {
        @unlink($path);
    }
});

test('default follows config instead of a hardcoded number', function () {
    config(['ops.ci_stale_hours' => 9]);
    $path = writeTempOpsSnapshot(now()->subHours(3)->toIso8601String());
    config(['ops.paths.ops_snapshot' => $path]);

    try {
        $exit = Artisan::call('ops:import-snapshot', ['--dry-run' => true]);

        expect($exit)->toBe(0)
            ->and(Artisan::output())->not->toContain('快照已陈旧');
    } finally {
        @unlink($path);
    }
});
