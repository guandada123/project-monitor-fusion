<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>项目日报 — {{ $report['date'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a; color: #e2e8f0; padding: 40px; line-height: 1.6;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 {
            font-size: 28px; font-weight: 700; margin-bottom: 8px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .date { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
        .card {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px; padding: 24px; margin-bottom: 20px;
        }
        h2 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #a78bfa; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .stat-item {
            text-align: center; padding: 16px 8px;
            background: rgba(255,255,255,0.03); border-radius: 12px;
        }
        .stat-value { font-size: 24px; font-weight: 700; color: #818cf8; }
        .stat-label { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .project-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .project-row:last-child { border-bottom: none; }
        .proj-name { font-weight: 600; }
        .proj-status { font-size: 12px; color: #94a3b8; }
        .progress-bar {
            width: 120px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;
        }
        .progress-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .risk-item { padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 14px; }
        .risk-critical { background: rgba(239,68,68,0.15); border-left: 3px solid #ef4444; }
        .risk-high { background: rgba(249,115,22,0.15); border-left: 3px solid #f97316; }
        .risk-medium { background: rgba(234,179,8,0.15); border-left: 3px solid #eab308; }
        .risk-low { background: rgba(34,197,94,0.15); border-left: 3px solid #22c55e; }
        .sev-tag {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 11px; font-weight: 600; margin-right: 8px;
        }
        .sev-critical { background: #ef4444; color: #fff; }
        .sev-high { background: #f97316; color: #fff; }
        .sev-medium { background: #eab308; color: #000; }
        .sev-low { background: #22c55e; color: #000; }
        .footer { text-align: center; color: #475569; font-size: 12px; margin-top: 32px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Project Monitor 项目日报</h1>
    <div class="date">{{ $report['date'] }} · 由 Project Monitor 自动生成</div>

    {{-- Summary --}}
    <div class="card">
        <h2>📈 总览</h2>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value">{{ $summary['active_projects'] }}</div>
                <div class="stat-label">活跃项目</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ $summary['todo_tasks'] }}</div>
                <div class="stat-label">待办任务</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ $summary['in_progress_tasks'] }}</div>
                <div class="stat-label">进行中</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ $summary['done_tasks'] }}</div>
                <div class="stat-label">已完成</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: {{ $summary['overdue_tasks'] > 0 ? '#f97316' : '#22c55e' }}">{{ $summary['overdue_tasks'] }}</div>
                <div class="stat-label">延期</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: {{ $summary['critical_risks'] > 0 ? '#ef4444' : '#22c55e' }}">{{ $summary['open_risks'] }}</div>
                <div class="stat-label">开放风险</div>
            </div>
            {{-- 2026-10-31：tasks 表无数据时完成率恒为 0，日报里显示 0% 像全线停摆。
                 改为展示采集到的代码规模。 --}}
            <div class="stat-item">
                @if(($summary['total_tasks'] ?? 0) > 0)
                    <div class="stat-value">{{ round(($summary['done_tasks'] / max(1, $summary['total_tasks'])) * 100, 1) }}%</div>
                    <div class="stat-label">完成率</div>
                @else
                    <div class="stat-value">{{ number_format(($summary['total_loc'] ?? 0) / 10000, 1) }}</div>
                    <div class="stat-label">代码万行</div>
                @endif
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ $summary['critical_risks'] }}</div>
                <div class="stat-label">严重风险</div>
            </div>
        </div>
    </div>

    {{-- Projects --}}
    <div class="card">
        <h2>📋 项目详情</h2>
        @foreach($report['projects'] as $p)
        <div class="project-row">
            <div>
                <div class="proj-name">{{ $p['name'] }}</div>
                <div class="proj-status">{{ $p['status'] === 'active' ? '🟢 活跃' : ($p['status'] === 'pending' ? '🔵 待启动' : '⚫ 归档') }}</div>
            </div>
            <div style="text-align: center;">
                @if(!empty($p['has_task_data']))
                    <div style="font-size: 20px; font-weight: 700; color: #818cf8;">{{ $p['completion_rate'] }}%</div>
                    <div style="font-size: 11px; color: #64748b;">{{ $p['done'] }}/{{ $p['total'] }}</div>
                @else
                    <div style="font-size: 20px; font-weight: 700; color: #818cf8;">{{ ($p['code_loc'] ?? 0) > 0 ? number_format($p['code_loc'] / 10000, 1) : '—' }}</div>
                    <div style="font-size: 11px; color: #64748b;">{{ ($p['code_loc'] ?? 0) > 0 ? '万行' : '未采集' }} · {{ $p['commits_7d'] ?? 0 }} 提交</div>
                @endif
            </div>
            <div class="progress-bar">
                @if(!empty($p['has_task_data']))
                    <div class="progress-fill" style="width: {{ $p['completion_rate'] }}%"></div>
                @else
                    {{-- 无任务时不画进度条：空条会被读成「毫无进展」 --}}
                    <div style="font-size: 11px; color: #64748b; text-align: right;">近 7 天活跃</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Risks --}}
    @if(!empty($report['risks']))
    <div class="card">
        <h2>⚠️ 风险预警</h2>
        @foreach($report['risks'] as $r)
        <div class="risk-item risk-{{ $r['severity'] }}">
            <span class="sev-tag sev-{{ $r['severity'] }}">
                {{ ['critical' => '严重', 'high' => '高', 'medium' => '中', 'low' => '低'][$r['severity']] ?? '?' }}
            </span>
            <strong>{{ $r['title'] }}</strong>
            <span style="color: #94a3b8; font-size: 12px;"> — {{ $r['project_name'] }}</span>
        </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        🤖 由 Project Monitor 自动生成 · {{ now()->format('Y-m-d H:i') }}
    </div>
</div>
</body>
</html>
