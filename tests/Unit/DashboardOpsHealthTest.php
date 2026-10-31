<?php

namespace Tests\Unit;

use App\Livewire\Dashboard;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Dashboard::loadOpsSnapshot() 的三态行为。
 *
 * 为什么值得测：这段逻辑决定面板显示「系统健康」还是「数据不可用」。
 * 它有三条路径（正常 / 文件不可读 / JSON 坏），其中最危险的是**静默显示空值**——
 * 面板看起来正常、实际没有任何数据。本次审计把它列为 P1 的同类问题。
 *
 * 不用 RefreshDatabase：本测试只读临时文件，不碰数据库。
 */
class DashboardOpsHealthTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        parent::tearDown();
    }

    /** 把快照写到临时文件并让 config 指向它，返回加载后的 opsHealth。 */
    private function loadWith(?string $json, bool $missing = false): array
    {
        if ($missing) {
            config(['ops.paths.ops_snapshot' => '/nonexistent/ops_snapshot.json']);
        } else {
            $f = tempnam(sys_get_temp_dir(), 'ops_snap_').'.json';
            file_put_contents($f, (string) $json);
            $this->tmpFiles[] = $f;
            config(['ops.paths.ops_snapshot' => $f]);
        }

        $c = new Dashboard;
        (new ReflectionMethod($c, 'loadOpsSnapshot'))->invoke($c);

        return (new ReflectionProperty($c, 'opsHealth'))->getValue($c);
    }

    public function test_missing_snapshot_marks_unavailable_with_reason(): void
    {
        $h = $this->loadWith(null, missing: true);

        $this->assertFalse($h['available'], '快照缺失时不能声称可用');
        $this->assertNotEmpty($h['note'], '必须给出不可用的原因，不能静默');
        $this->assertStringContainsString('不可读', $h['note']);
    }

    public function test_broken_json_marks_unavailable_and_says_why(): void
    {
        $h = $this->loadWith('{not valid json');

        $this->assertFalse($h['available']);
        $this->assertStringContainsString('解析失败', $h['note']);
    }

    public function test_normal_snapshot_extracts_checks_cost_credit_discovery(): void
    {
        $payload = [
            'generated_at' => (new \DateTimeImmutable('now'))->format('c'),
            'checks' => [
                'source_ts' => '2026-10-31T11:43:48+08:00',
                'counts' => ['total' => 34, 'pass' => 30, 'fail' => 1, 'observe' => 3],
                'items' => [
                    ['name' => 'A', 'status' => 'fail', 'alert_count' => 2, 'alerts' => ['boom']],
                    ['name' => 'B', 'status' => 'pass'],
                    ['name' => 'C', 'status' => 'observe', 'note' => 'watching'],
                ],
            ],
            'cost' => [
                'month' => '2026-10',
                'by_class' => ['human' => ['cny' => 100.0], 'auto' => ['cny' => 50.0]],
                'coverage' => ['join_rate' => 0.99],
            ],
            'credit' => ['used' => 1000, 'budget' => 2000, 'pct' => 50.0, 'tier' => 'x'],
            'discovery' => [
                'counts' => ['pool' => 10, 'pending' => 2, 'landed' => 8, 'new_7d' => 3, 'landed_7d' => 4],
            ],
        ];

        $h = $this->loadWith(json_encode($payload));

        $this->assertTrue($h['available']);
        $this->assertFalse($h['stale'], '刚生成的快照不该被判陈旧');
        $this->assertSame(34, $h['counts']['total']);
        $this->assertSame(1, $h['counts']['fail']);
        // 只列 fail 与 observe，pass 不进清单
        $this->assertCount(1, $h['failing']);
        $this->assertCount(1, $h['observing']);
        $this->assertSame('A', $h['failing'][0]['name']);

        $this->assertSame(150.0, $h['cost']['total_cny']);
        $this->assertSame(50.0, $h['credit']['pct']);
        $this->assertSame(2, $h['discovery']['pending']);
        $this->assertSame('2026-10-31T11:43:48+08:00', $h['checks_source_ts']);
    }

    public function test_snapshot_without_optional_blocks_still_available(): void
    {
        // 只给 checks，缺 cost/credit/discovery —— 不能因此整块判不可用
        $payload = ['checks' => ['counts' => ['total' => 1, 'pass' => 1]]];

        $h = $this->loadWith(json_encode($payload));

        $this->assertTrue($h['available']);
        $this->assertNull($h['cost']);
        $this->assertNull($h['credit']);
        $this->assertNull($h['discovery'], '缺 discovery 应为 null，而不是报错或 0 条');
    }

    public function test_stale_snapshot_is_flagged(): void
    {
        $old = (new \DateTimeImmutable('-10 hours'))->format('c');
        $h = $this->loadWith(json_encode([
            'generated_at' => $old,
            'checks' => ['counts' => ['total' => 1, 'pass' => 1]],
        ]));

        $this->assertTrue($h['available']);
        $this->assertTrue($h['stale'], '超过 6 小时必须标记陈旧');
        $this->assertGreaterThan(6, $h['age_hours']);
    }
}
