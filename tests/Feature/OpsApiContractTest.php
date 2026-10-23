<?php

namespace Tests\Feature;

use App\Support\Ops\OpsProbe;
use Tests\TestCase;

/**
 * OpsProbe / ops API 的「不撒谎」契约（2026-09-28 建）。
 *
 * 背景：pmf 跑在容器里，这套探测原先写死 `127.0.0.1` + 宿主绝对路径 →
 * ① 三个服务恒 DOWN；② 体积恒 `0 MB`，而旧 `sizeProgress` 把 0 当"很好" →
 * roadmap 直接显示**体积目标 100% 完成**（把"取不到证"渲染成了"达标"）。
 * 本文件把两条契约钉住：取不到就报 MISCONFIG / n/a / null，**不许报 0 或 healthy**。
 */
class OpsApiContractTest extends TestCase
{
    public function test_http_probe_reports_misconfig_when_probe_host_unusable(): void
    {
        // 空主机名是确定性可复现的「探测入口不可用」——不能用 .invalid 域名：
        // 运营商 wildcard DNS 会把它解析出 IP，"解析失败"这一层根本测不到（实测踩过）。
        config(['ops.probe_host' => '']);

        $r = OpsProbe::httpProbe(8000, '/health');

        $this->assertSame('MISCONFIG', $r['status'], '探测入口不可用时必须报 MISCONFIG，不能把服务标成 DOWN');
    }

    public function test_probe_host_resolvable_false_for_empty_host(): void
    {
        config(['ops.probe_host' => '']);
        $this->assertFalse(OpsProbe::probeHostResolvable());
    }

    public function test_dir_size_is_null_and_not_zero_when_path_missing(): void
    {
        $missing = '/definitely-not-here-'.uniqid();

        $this->assertNull(OpsProbe::dirSizeMb($missing), '取不到体积必须返回 null，不能是 0');
        $this->assertSame('n/a', OpsProbe::dirSizeHuman($missing), '对外显示必须是 n/a，不能是 0 MB');
    }

    public function test_roadmap_size_progress_is_null_not_100_when_unavailable(): void
    {
        config([
            'ops.paths.repos.qts' => '/definitely-not-here-'.uniqid(),
            'ops.paths.repos.stockinsight' => '/definitely-not-here-'.uniqid(),
        ]);

        $resp = $this->getJson('/api/ops/roadmap');
        $resp->assertOk();

        $goals = collect($resp->json('data.goals'))->keyBy('name');
        foreach (['QTS 仓库体积', 'StockInsight 体积'] as $name) {
            $this->assertTrue($goals->has($name), "roadmap 缺目标: {$name}");
            $this->assertNull(
                $goals[$name]['progress'],
                "{$name}: 体积取不到时应为 null（未知），旧实现返回 100 会伪造「达标」"
            );
            $this->assertSame('n/a', $goals[$name]['current']);
        }
    }

    public function test_overview_reports_unknown_and_na_instead_of_fake_health(): void
    {
        // 只断言**与网络无关**的那部分（宿主路径缺失 → unknown/n/a）。
        // 「探测入口不通时 status=unknown」依赖网络行为，交给 MISCONFIG 那条单测，不在这里赌 DNS。
        config([
            'ops.probe_host' => '',
            'ops.paths.repos.claw' => '/definitely-not-here-'.uniqid(),
        ]);

        $resp = $this->getJson('/api/ops/overview');
        $resp->assertOk();

        $this->assertSame('unknown', $resp->json('data.projects.claw.status'));
        $this->assertSame('n/a', $resp->json('data.projects.claw.size'));
        $this->assertFalse($resp->json('data.probe_input.probe_host_resolvable'));
    }
}
