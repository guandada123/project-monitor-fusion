@php($isPdf = ($isPdf ?? false))
<!DOCTYPE html>
<html lang="zh-CN" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>项目报告 - {{ $project->name }}</title>
    <style>
        :root {
            --r-bg: #ffffff; --r-card-bg: #fafafa; --r-text: #111827;
            --r-sub: #6b7280; --r-border: #e5e7eb; --r-th-bg: #f8fafc;
            --r-green-bg: #dcfce7; --r-green-text: #166534;
            --r-red-bg: #fee2e2; --r-red-text: #991b1b;
            --r-amber-bg: #fef3c7; --r-amber-text: #92400e;
        }
        html.dark {
            --r-bg: #0f172a; --r-card-bg: #1e293b; --r-text: #f1f5f9;
            --r-sub: #94a3b8; --r-border: #334155; --r-th-bg: #1e293b;
            --r-green-bg: rgba(16,185,129,0.2); --r-green-text: #34d399;
            --r-red-bg: rgba(239,68,68,0.2); --r-red-text: #f87171;
            --r-amber-bg: rgba(245,158,11,0.2); --r-amber-text: #fbbf24;
        }
        html, body { background: var(--r-bg); color: var(--r-text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif; }
        body { margin: 24px; line-height: 1.6; }
        h1 { font-size: 28px; margin: 0 0 16px; }
        h2 { font-size: 20px; margin: 24px 0 8px; border-left: 4px solid #2563eb; padding-left: 8px; }
        .muted { color: var(--r-sub); }
        .kpi { display: flex; gap: 16px; flex-wrap: wrap; }
        .kpi .card { flex: 1 1 160px; border: 1px solid var(--r-border); border-radius: 12px; padding: 12px 16px; background: var(--r-card-bg); }
        .kpi .value { font-size: 24px; font-weight: 700; }
        .kpi .label { font-size: 12px; color: var(--r-sub); }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid var(--r-border); padding: 8px 6px; font-size: 12px; vertical-align: top; }
        th { background: var(--r-th-bg); text-align: left; color: var(--r-sub); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; }
        .badge.green { background: var(--r-green-bg); color: var(--r-green-text); }
        .badge.red { background: var(--r-red-bg); color: var(--r-red-text); }
        .badge.amber { background: var(--r-amber-bg); color: var(--r-amber-text); }
        .footer { margin-top: 24px; font-size: 12px; color: var(--r-sub); }
        .page-break { page-break-before: always; }
    </style>
    {{-- Theme init for HTML preview mode --}}
    @unless($isPdf)
    <script>
        (function(){
            const t = localStorage.getItem('pm-theme') || 'system';
            const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) document.documentElement.classList.add('dark');
        })();
    </script>
    @endunless
</head>
<body>
    @unless($isPdf)
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <h1 style="margin:0;">📊 {{ $project->name }} — 项目报告</h1>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="/projects/{{ $project->slug }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;border:1px solid var(--r-border);background:var(--r-card-bg);color:var(--r-text);font-size:13px;font-weight:500;text-decoration:none;">← 返回详情</a>
        <button id="btn-push-report" onclick="pushReport('{{ $project->slug }}')" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;border:1px solid var(--r-border);background:var(--r-card-bg);color:var(--r-text);font-size:13px;font-weight:500;cursor:pointer;">📤 推送飞书</button>
        <a href="/projects/{{ $project->slug }}/report.pdf" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;border:1px solid var(--r-border);background:var(--r-card-bg);color:var(--r-text);font-size:13px;font-weight:500;text-decoration:none;">📥 下载 PDF</a>
      </div>
    </div>
    @else
    <h1>📊 {{ $project->name }} — 项目报告</h1>
    @endunless
    <div class="muted">Slug：{{ $project->slug }} ｜ 生成时间：{{ $generatedAt }}</div>

    {{-- 📊 图表可视化区 — SVG 直接在 PDF 中渲染 --}}
    <h2>📊 关键指标</h2>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;margin-bottom:16px;">
        <div style="flex:0 0 auto;">{!! $charts['donut'] !!}</div>
        <div style="flex:0 0 auto;display:flex;flex-direction:column;gap:8px;">
            {!! $charts['kpi_done'] !!}
            {!! $charts['kpi_open'] !!}
            {!! $charts['kpi_risk'] !!}
        </div>
    </div>
    <div style="margin-bottom:8px;">{!! $charts['progress'] !!}</div>

    <h2>📋 任务分布</h2>
    <div style="margin-bottom:12px;">{!! $charts['bars_tasks'] !!}</div>

    <h2>⚠️ 风险概览</h2>
    <div style="margin-bottom:12px;">{!! $charts['bars_risks'] !!}</div>

    <h2>任务清单（按截止时间）</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 40%">标题</th>
                <th style="width: 15%">状态</th>
                <th style="width: 15%">优先级</th>
                <th style="width: 15%">预计/已用</th>
                <th style="width: 15%">截止时间</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $t)
                <tr>
                    <td>{{ $t->title }}</td>
                    <td>
                        @php($status = strtolower($t->status ?? 'open'))
                        <span class="badge {{ in_array($status, ['done','closed','merged','resolved']) ? 'green' : (in_array($status,['in_progress','doing']) ? 'amber' : 'red') }}">{{ $status }}</span>
                    </td>
                    <td>{{ $t->priority ?? '-' }}</td>
                    <td>{{ $t->estimate ?? '-' }} / {{ $t->spent ?? '-' }}</td>
                    <td>{{ optional($t->due_at)->format('Y-m-d') ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">暂无任务</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>风险列表（按等级）</h2>
    <table>
        <thead>
        <tr>
            <th style="width: 55%">风险描述</th>
            <th style="width: 15%">等级</th>
            <th style="width: 15%">状态</th>
            <th style="width: 15%">创建时间</th>
        </tr>
        </thead>
        <tbody>
        @forelse($risks as $r)
            <tr>
                <td>{{ $r->title ?? $r->detail ?? '-' }}</td>
                <td><span class="badge {{ ($r->severity ?? 'low') === 'high' ? 'red' : (($r->severity ?? 'low') === 'medium' ? 'amber' : 'green') }}">{{ $r->severity ?? 'low' }}</span></td>
                <td>{{ $r->status ?? '-' }}</td>
                <td>{{ optional($r->created_at)->format('Y-m-d') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">暂无风险</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="footer muted">本报告为系统自动生成{{ $isPdf ? '（PDF 导出）' : '' }}，仅供项目跟踪使用。</div>
    @unless($isPdf)
    <script>
    async function pushReport(slug) {
      var btn = document.getElementById('btn-push-report');
      var orig = btn.innerHTML;
      btn.innerHTML = '⏳ 推送中...';
      btn.disabled = true;
      btn.style.opacity = '0.6';

      try {
        var token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var resp = await fetch('/projects/' + slug + '/push-feishu', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
        });
        var data = await resp.json();
        if (data.ok) {
          btn.innerHTML = '✅ 已推送';
          btn.style.background = 'var(--r-green-bg)';
          btn.style.borderColor = 'var(--r-green-text)';
        } else {
          btn.innerHTML = '❌ 失败';
          btn.style.background = 'var(--r-red-bg)';
          btn.style.borderColor = 'var(--r-red-text)';
          alert(data.message || '推送失败');
        }
      } catch(e) {
        btn.innerHTML = '❌ 网络错误';
        btn.style.background = 'var(--r-red-bg)';
        btn.style.borderColor = 'var(--r-red-text)';
      }
      setTimeout(function() {
        btn.innerHTML = orig;
        btn.style.background = '';
        btn.style.borderColor = '';
        btn.style.opacity = '1';
        btn.disabled = false;
      }, 2500);
    }
    </script>
    @endunless
</body>
</html>
