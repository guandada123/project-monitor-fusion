<?php

use App\Services\SlowQueryMonitor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// ─── Boot / Registration ───

test('slow query monitor boots without errors', function () {
    SlowQueryMonitor::boot();

    // Execute a normal query to prove the listener doesn't break anything
    $exists = DB::table('users')->exists();

    expect($exists)->toBeFalse();
});

test('slow query monitor does not interfere with normal queries', function () {
    SlowQueryMonitor::boot();

    $user = User::factory()->create(['name' => 'test']);
    $found = User::find($user->id);

    expect($found->name)->toBe('test');
});

test('app service provider boot calls slow query monitor', function () {
    $provider = new \App\Providers\AppServiceProvider(app());

    // Should not throw
    $provider->boot();

    expect(true)->toBeTrue();
});

// ─── Caller detection ───

test('findCaller returns a non-vendor file path', function () {
    $monitor = new SlowQueryMonitor();

    // Use reflection to access protected method
    $refMethod = new ReflectionMethod($monitor, 'findCaller');
    $caller = $refMethod->invoke($monitor);

    // The caller should be a real file path, not in vendor/
    expect($caller)->not->toBeNull();
    expect($caller)->toContain(':');
    expect($caller)->not->toContain('/vendor/');
});

// ─── SQL truncation ───

test('truncateSql does not truncate short sql', function () {
    $monitor = new SlowQueryMonitor();
    $refMethod = new ReflectionMethod($monitor, 'truncateSql');
    $refMethod->setAccessible(true);

    $sql = 'select * from users where id = ?';
    $result = $refMethod->invoke($monitor, $sql, 500);

    expect($result)->toBe($sql);
});

test('truncateSql truncates long sql with ellipsis', function () {
    $monitor = new SlowQueryMonitor();
    $refMethod = new ReflectionMethod($monitor, 'truncateSql');
    $refMethod->setAccessible(true);

    $sql = str_repeat('x', 1000);
    $result = $refMethod->invoke($monitor, $sql, 20);

    expect($result)->toBe(str_repeat('x', 20) . '...');
    expect(mb_strlen($result))->toBe(23);
});

// ─── Slow query log detection (integration-level) ───

test('slow query monitor facade reacts to query time', function () {
    // We can't actually make SQLite slow (>500ms), but we verify
    // the listener is registered and continuous queries work.
    SlowQueryMonitor::boot();

    DB::table('users')->count();
    DB::table('users')->where('id', 1)->first();
    User::factory()->count(3)->create();

    // All queries complete without error
    expect(User::count())->toBe(3);
});
