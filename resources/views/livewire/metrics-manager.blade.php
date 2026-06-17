<div style="max-width:1100px;margin:0 auto;padding:40px 24px 80px;">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:12px;">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <a href="/" style="color:var(--dim);text-decoration:none;font-size:13px;" class="hover-underline">← Dashboard</a>
        <span style="color:var(--dim);">/</span>
        <a href="/projects/{{ $slug }}" style="color:var(--dim);text-decoration:none;font-size:13px;" class="hover-underline">{{ $project->name }}</a>
        <span style="color:var(--dim);">/</span>
        <span style="font-size:13px;font-weight:600;">指标管理</span>
      </div>
      <h1 style="margin:0;font-size:28px;font-weight:700;letter-spacing:-0.5px;">📐 指标管理 <span style="font-weight:400;color:var(--dim);font-size:18px;">· {{ $project->name }}</span></h1>
    </div>
    <div style="display:flex;gap:8px;">
      <button wire:click="computeNow" style="padding:10px 18px;border-radius:10px;border:1px solid var(--stroke);background:var(--card);font-size:13px;font-weight:600;cursor:pointer;color:var(--text);transition:all .2s;" onmouseover="this.style.boxShadow='var(--shadow)'" onmouseout="this.style.boxShadow='none'">
        🔄 立即计算
      </button>
      <button wire:click="startCreate" style="padding:10px 20px;border-radius:10px;border:none;background:var(--accent);color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;" onmouseover="this.style.opacity='0.9';this.style.transform='translateY(-1px)'" onmouseout="this.style.opacity='1';this.style.transform='none'">
        + 新建指标
      </button>
    </div>
  </div>

  {{-- Flash --}}
  @if (session()->has('message'))
    <div style="padding:12px 18px;border-radius:10px;background:var(--success);color:#fff;font-size:13px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px;">{{ session('message') }}</div>
  @endif
  @if (session()->has('error'))
    <div style="padding:12px 18px;border-radius:10px;background:var(--danger);color:#fff;font-size:13px;font-weight:500;margin-bottom:20px;">{{ session('error') }}</div>
  @endif

  {{-- Form Modal --}}
  @if ($showForm)
    <div style="position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;" wire:click.self="cancelForm">
      <div style="background:var(--card);border-radius:16px;border:1px solid var(--stroke);width:600px;max-height:80vh;overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <h3 style="margin:0 0 20px;font-size:20px;font-weight:700;">{{ $editingId ? '编辑指标' : '新建自定义指标' }}</h3>

        {{-- 基础字段 --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          <label style="display:block;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">名称</span>
            <input wire:model="formName" placeholder="如：高优任务数" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
          </label>
          <label style="display:block;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">标识符 (Key)</span>
            <input wire:model="formKey" placeholder="high_priority_count" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
          </label>
        </div>

        {{-- 类型选择 --}}
        <label style="display:block;margin-bottom:16px;">
          <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">计算类型</span>
          <select wire:model.live="formType" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
            @foreach ($metricTypes as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </label>

        {{-- 按类型展开不同配置 --}}
        @if (in_array($formType, ['count_tasks']))
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">筛选任务状态</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach ($statusOptions as $s)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formStatuses" value="{{ $s }}" style="accent-color:var(--accent);">
                  <span>{{ $s }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">筛选优先级</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach ($priorityOptions as $p)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formPriorities" value="{{ $p }}" style="accent-color:var(--accent);">
                  <span>{{ $p }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @elseif ($formType === 'count_risks')
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">筛选风险状态</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach (['open', 'acknowledged', 'mitigated', 'closed'] as $s)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formStatuses" value="{{ $s }}" style="accent-color:var(--accent);">
                  <span>{{ $s }}</span>
                </label>
              @endforeach
            </div>
          </div>
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">筛选严重程度</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach ($severityOptions as $s)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formSeverities" value="{{ $s }}" style="accent-color:var(--accent);">
                  <span>{{ $s }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @elseif (in_array($formType, ['completion_rate', 'cycle_days']))
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">"已完成"包含哪些状态？</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach (['done', 'closed', 'completed'] as $s)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formDoneStatuses" value="{{ $s }}" style="accent-color:var(--accent);">
                  <span>{{ $s }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @elseif ($formType === 'wip_count')
          <div style="margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:6px;display:block;">"进行中"包含哪些状态？</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              @foreach (['in_progress', 'doing', 'open'] as $s)
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:4px 10px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);transition:all .15s;" class="chip-hover">
                  <input type="checkbox" wire:model.live="formWipStatuses" value="{{ $s }}" style="accent-color:var(--accent);">
                  <span>{{ $s }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @elseif ($formType === 'formula')
          <label style="display:block;margin-bottom:16px;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">算术表达式 <span style="font-weight:400;color:var(--dim);">— 支持 +, -, *, /, ( )，暂不支持变量引用</span></span>
            <input wire:model="formExpression" placeholder="如：(5 + 3) * 2 / 4" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;">
          </label>
        @endif

        {{-- 显示/单位 --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          <label style="display:block;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">显示标签</span>
            <input wire:model="formLabel" placeholder="如：高优任务数" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
          </label>
          <label style="display:block;">
            <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">单位</span>
            <input wire:model="formUnit" placeholder="个 / % / 天" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;">
          </label>
        </div>

        <label style="display:block;margin-bottom:20px;">
          <span style="font-size:12px;font-weight:600;color:var(--dim);margin-bottom:4px;display:block;">自动采集 cron <span style="font-weight:400;color:var(--dim);">— 留空则手动触发</span></span>
          <input wire:model="formSchedule" placeholder="0 8 * * 1-5" style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;font-family:monospace;">
        </label>

        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button wire:click="cancelForm" style="padding:10px 18px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;cursor:pointer;">取消</button>
          <button wire:click="saveMetric" style="padding:10px 24px;border-radius:8px;border:none;background:var(--accent);color:#fff;font-size:13px;font-weight:600;cursor:pointer;">{{ $editingId ? '更新' : '创建' }}</button>
        </div>
      </div>
    </div>
  @endif

  {{-- 快照历史 Modal --}}
  @if ($showHistory)
    <div style="position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;" wire:click.self="closeHistory">
      <div style="background:var(--card);border-radius:16px;border:1px solid var(--stroke);width:550px;max-height:70vh;overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h3 style="margin:0;font-size:18px;font-weight:700;">📈 历史快照</h3>
          <span style="font-size:12px;color:var(--dim);">{{ count($historySnapshots) }} 条记录</span>
        </div>
        @if (empty($historySnapshots))
          <p style="color:var(--dim);font-size:13px;">暂无历史快照，点击"立即计算"生成首批数据。</p>
        @else
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
              <tr style="border-bottom:1px solid var(--stroke);text-align:left;">
                <th style="padding:8px 12px;color:var(--dim);font-weight:600;">时间</th>
                <th style="padding:8px 12px;color:var(--dim);font-weight:600;">值</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($historySnapshots as $s)
                <tr style="border-bottom:1px solid var(--stroke);">
                  <td style="padding:8px 12px;">{{ \Carbon\Carbon::parse($s['captured_at'])->format('m-d H:i') }}</td>
                  <td style="padding:8px 12px;font-weight:600;">{{ $s['value']['value'] ?? '-' }}{{ $s['value']['unit'] ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
        <div style="margin-top:16px;text-align:right;">
          <button wire:click="closeHistory" style="padding:8px 18px;border-radius:8px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:13px;cursor:pointer;">关闭</button>
        </div>
      </div>
    </div>
  @endif

  {{-- 指标列表 --}}
  <div style="display:grid;gap:12px;margin-top:24px;">
    @forelse ($metrics as $metric)
      @php
        $val = $metric->value;
        $displayValue = $val['value'] ?? '—';
        $displayUnit = $val['unit'] ?? '';
        $displayLabel = $val['label'] ?? $metric->name;
        $displayDetail = $val['detail'] ?? '';
        $bgColors = match($metric->kind) {
          'system' => 'rgba(124,58,237,0.08)',
          default  => 'rgba(16,185,129,0.08)',
        };
        $borderColors = match($metric->kind) {
          'system' => 'rgba(124,58,237,0.25)',
          default  => 'rgba(16,185,129,0.3)',
        };
      @endphp
      <div data-animate style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:{{ $bgColors }};border:1px solid {{ $borderColors }};border-radius:12px;transition:all .2s;gap:12px;flex-wrap:wrap;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.06)'" onmouseout="this.style.boxShadow='none'">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
          <span style="font-size:11px;padding:3px 8px;border-radius:6px;font-weight:600;{{ $metric->kind === 'system' ? 'background:rgba(124,58,237,0.15);color:var(--accent);' : 'background:rgba(16,185,129,0.15);color:var(--success);' }}">{{ $metric->kind === 'system' ? '系统' : '自定义' }}</span>
          <div style="min-width:0;">
            <div style="font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $metric->name }}</div>
            <div style="font-size:11px;color:var(--dim);font-family:monospace;">{{ $metric->key }}</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
          <div style="text-align:right;">
            <div style="font-size:22px;font-weight:800;line-height:1.2;">{{ $displayValue }}<span style="font-size:14px;font-weight:400;color:var(--dim);">{{ $displayUnit }}</span></div>
            @if ($displayDetail)
              <div style="font-size:11px;color:var(--dim);">{{ $displayDetail }}</div>
            @endif
            @if ($metric->last_computed_at)
              <div style="font-size:10px;color:var(--dim);">上次计算 {{ $metric->last_computed_at->diffForHumans() }}</div>
            @endif
          </div>
          <div style="display:flex;gap:4px;">
            <button wire:click="showSnapshots({{ $metric->id }})" title="历史快照" style="padding:6px 8px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:11px;cursor:pointer;">📈</button>
            <button wire:click="startEdit({{ $metric->id }})" title="编辑" style="padding:6px 8px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);color:var(--text);font-size:11px;cursor:pointer;">✏️</button>
            @if ($metric->kind !== 'system')
              <button wire:click="deleteMetric({{ $metric->id }})" title="删除" style="padding:6px 8px;border-radius:6px;border:1px solid var(--stroke);background:var(--bg);color:var(--danger);font-size:11px;cursor:pointer;" onclick="return confirm('确定删除此指标？')">🗑</button>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div style="text-align:center;padding:60px 20px;color:var(--dim);">
        <div style="font-size:48px;margin-bottom:12px;">📐</div>
        <p style="font-size:14px;margin:0;">还没有自定义指标，点击"新建指标"开始创建</p>
      </div>
    @endforelse
  </div>

</div>
