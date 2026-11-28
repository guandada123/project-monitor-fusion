<?php

use App\Services\SlowQueryMonitor;

/**
 * 合成睡眠语句不进告警 —— 判据的四组边界。
 *
 * 背景（2026-11-28）：慢查询告警里混进了 6 条 1206–1705ms，溯源是取证探针自己
 * 造的 `select pg_sleep(1.2…1.7)`。这类行要落库（作为停顿证据），但**不能推飞书**，
 * 否则「告警里一半是自己造的」。Claw 仓 scripts/pgsql_stall_watch.py 的
 * is_probe_query() 是同一条口径，两边必须一致 —— 一边沉默一边告警等于没对齐。
 */
describe('SlowQueryMonitor probe filter', function () {
    it('treats pg_sleep as probe', function () {
        $monitor = app(SlowQueryMonitor::class);
        $m = new ReflectionMethod($monitor, 'isProbeQuery');
        $m->setAccessible(true);

        // ① 小写原样
        expect($m->invoke($monitor, 'select pg_sleep(1.2)'))->toBeTrue();
        // ② 大写 + 多空格 + 参数内空格（探针实际发出的形态可能被格式化过）
        expect($m->invoke($monitor, '  SELECT   pg_sleep( 1.7 )  '))->toBeTrue();
    });

    it('does not treat business sql as probe', function () {
        $monitor = app(SlowQueryMonitor::class);
        $m = new ReflectionMethod($monitor, 'isProbeQuery');
        $m->setAccessible(true);

        expect($m->invoke($monitor, 'select * from jobs where id = 1'))->toBeFalse();
        // 边界：前缀相近但不同名的函数不能被误判成探针，否则真慢查询会被静默吞掉
        expect($m->invoke($monitor, 'select pg_sleep_extra()'))->toBeFalse();
        // 边界：pg_sleep 出现在中段（不是语句开头）也不算 —— 探针只会是整条语句
        expect($m->invoke($monitor, 'select 1 from t where f = pg_sleep(2)'))->toBeFalse();
    });
});
