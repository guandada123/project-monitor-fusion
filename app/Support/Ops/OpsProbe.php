<?php

namespace App\Support\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OpsProbe — 外部探测 / 宿主数据的**唯一实现**（2026-09-28 建）
 *
 * 为什么要有这个类：同一套"探测 + 宿主路径"逻辑原先**抄了两份**
 * （`OpsHealthScanCommand` 与 `OpsApiController`），两份都写死 `127.0.0.1` 与宿主绝对路径 →
 * 容器部署下**同时恒失败**，而且改一处不改另一处。现在两边共用这里。
 *
 * 两条纪律（都是踩过的坑）：
 *  ① **取不到证 ≠ 健康**：路径/主机不可得时返回 `null` / `'n/a'` / `MISCONFIG`，
 *     **绝不许返回 0 或 healthy** —— 旧实现把取不到的体积当 0，导致面板 roadmap 显示 100% 完成。
 *  ② **输入自证**：探测前先确认"输入还在不在"（探测主机能否解析、路径是否存在），
 *     否则报出来的 DOWN 是"配置错"而非"服务挂"，把人引向错误的排查方向。
 */
class OpsProbe
{
    /** 探测主机（默认 host.docker.internal；见 config/ops.php 说明） */
    public static function probeHost(): string
    {
        return (string) config('ops.probe_host', 'host.docker.internal');
    }

    /** HTTP 探测目标（单一真源在 config/ops.php） */
    public static function httpTargets(): array
    {
        return config('ops.http_targets', []);
    }

    /** 仓库路径（单一真源在 config/ops.php） */
    public static function repoPath(string $key): ?string
    {
        return config("ops.paths.repos.{$key}");
    }

    public static function path(string $key): ?string
    {
        return config("ops.paths.{$key}");
    }

    /** 探测主机是否可解析 —— **只是提示，不是判据**。
     *
     * ⚠️ 实测（2026-09-28）：运营商 wildcard DNS 会把 `no-such-host.invalid` 也解析出一个 IP，
     * 于是"解析成功"变成一句空话。真正的分类判据在 `httpProbe()` 里按**失败原因**区分。
     */
    public static function probeHostResolvable(): bool
    {
        $host = self::probeHost();
        if ($host === '') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return gethostbyname($host) !== $host;
    }

    /**
     * 单端口 HTTP 探测。
     *
     * @return array{status:string,detail:string} status ∈ UP|DEGRADED|DOWN|MISCONFIG
     */
    public static function httpProbe(int $port, string $path = '/health'): array
    {
        $host = self::probeHost();
        $url = "http://{$host}:{$port}{$path}";
        try {
            $resp = Http::timeout(3)->get($url);

            return [
                'status' => $resp->successful() ? 'UP' : 'DEGRADED',
                'detail' => "HTTP {$resp->status()} ({$url})",
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // 「解析不出主机」= 我这儿根本连不出去（探测入口/配置错），不是"服务挂了"。
            // 分成两类，才不会把人引去查一个根本没问题的服务。
            $unresolvable = stripos($msg, 'resolve host') !== false
                || stripos($msg, 'name or service not known') !== false
                || stripos($msg, 'nodename nor servname') !== false;
            if ($host === '' || $unresolvable) {
                return ['status' => 'MISCONFIG', 'detail' => "探测主机不可用: {$msg} ({$url})"];
            }

            return ['status' => 'DOWN', 'detail' => "{$msg} ({$url})"];
        }
    }

    /** 向后兼容的布尔形态（面板用） */
    public static function httpOk(int $port, string $path = '/health'): bool
    {
        return self::httpProbe($port, $path)['status'] === 'UP';
    }

    /**
     * 目录体积（MB）。**取不到就返回 null**，不返回 0。
     * 结果按 config('ops.dir_size_cache_minutes') 缓存；`fresh=true` 跳过缓存强制实测。
     */
    public static function dirSizeMb(string $path, bool $fresh = false): ?int
    {
        if (! is_dir($path)) {
            return null;
        }
        $key = 'ops:dirsize:'.md5($path);
        $ttl = (int) config('ops.dir_size_cache_minutes', 360);
        if (! $fresh && $ttl > 0) {
            $hit = Cache::get($key);
            if (is_array($hit) && isset($hit['mb'])) {
                return (int) $hit['mb'];
            }
        }
        $out = [];
        $rc = 0;
        exec('du -sm '.escapeshellarg($path).' 2>/dev/null', $out, $rc);
        if ($rc !== 0 || ! isset($out[0])) {
            return null;
        }
        if (! preg_match('/^\s*(\d+)/', $out[0], $m)) {
            return null;
        }
        $mb = (int) $m[1];
        if ($ttl > 0) {
            Cache::put($key, ['mb' => $mb, 'measured_at' => now()->toIso8601String()], now()->addMinutes($ttl));
        }

        return $mb;
    }

    /** 人类可读体积；取不到返回 'n/a'（**不是 '0 MB'**） */
    public static function dirSizeHuman(string $path): string
    {
        $mb = self::dirSizeMb($path);

        return $mb === null ? (string) config('ops.size_unavailable', 'n/a') : self::human($mb);
    }

    public static function human(int $mb): string
    {
        return $mb >= 1024 ? round($mb / 1024, 1).' GB' : $mb.' MB';
    }

    /** 目录是否可见（用来把"路径没挂进来"与"目录真的空了"分开） */
    public static function dirVisible(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * workbuddy.db 的**只读快照**路径（TTL 刷新），失败返回 null。
     *
     * 为什么不能直接读挂进来的那个文件：该库是 **WAL 模式**，SQLite 打开时即使在只读用途下
     * 也要在同目录创建/写 `-wal`/`-shm` → 只读挂载下报
     * `SQLSTATE[HY000]: General error: 8 attempt to write a readonly database`
     * （实测：`PDO::SQLITE_ATTR_OPEN_FLAGS => OPEN_READONLY` 也救不了）。
     * 唯一可行的只读组合是「连 `-wal` 一起挂」，但 `-wal` 在 checkpoint 后会被删除，
     * 而 bind mount 的源不存在时 Docker 会建成**目录** → 不可靠。
     * 故：复制 db(+wal/shm) 到容器可写区，按 TTL 刷新，并在 API 里**如实标注这是快照**。
     *
     * ⚠️ 只挂了 `.db`（**没有**挂 `-wal`）：bind mount 的源文件不存在时 Docker 会在**宿主**上
     * 建成目录，反过来会把宿主的 SQLite 弄坏 —— 这个代价远大于收益。
     * 因此快照**可能落后于宿主最后一次 checkpoint**，调用方必须带 `snapshot_at` 标注。
     */
    public static function workbuddyDbSnapshot(): ?string
    {
        $src = (string) OpsProbe::path('workbuddy_db');
        if ($src === '' || ! is_file($src)) {
            return null;
        }
        $dir = storage_path('app/ops-snapshot');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return null;
        }
        $dst = $dir.'/'.basename($src);
        $ttl = max(0, (int) config('ops.db_snapshot_minutes', 10)) * 60;
        $stale = ! is_file($dst) || ($ttl > 0 && (time() - (int) filemtime($dst)) > $ttl);
        if ($stale) {
            if (! @copy($src, $dst)) {
                return is_file($dst) ? $dst : null;   // 复制失败但旧快照还在 → 用旧的（调用方会带 snapshot_at）
            }
            foreach (['-wal', '-shm'] as $suffix) {
                $s = $src.$suffix;
                $d = $dst.$suffix;
                if (is_file($s)) {
                    @copy($s, $d);
                } elseif (is_file($d)) {
                    @unlink($d);
                }
            }
            @touch($dst);
        }

        return is_file($dst) ? $dst : null;
    }

    /**
     * 自检：本部署下探测输入是否齐备。**故意做成显式输出**，
     * 这样"检查器空转"会以 MISCONFIG/缺失清单的形式暴露，而不是安静地全绿/全红。
     */
    public static function selfCheck(): array
    {
        $host = self::probeHost();
        $missing = [];
        foreach (['workbuddy_db', 'state_anchor'] as $k) {
            $p = self::path($k);
            if (! $p || ! file_exists($p)) {
                $missing[] = "{$k}={$p}";
            }
        }
        foreach (['claw', 'qts', 'stockinsight'] as $k) {
            $p = self::repoPath($k);
            if (! $p || ! is_dir($p)) {
                $missing[] = "repo:{$k}={$p}";
            }
        }

        return [
            'probe_host' => $host,
            'probe_host_resolvable' => self::probeHostResolvable(),
            'missing_inputs' => $missing,
        ];
    }
}
