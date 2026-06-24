<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Services\RiskSuggestionService;
use App\Traits\AuthorizesResourceAccess;
use Livewire\Component;
use Livewire\WithPagination;

class RiskManager extends Component
{
    use AuthorizesResourceAccess, WithPagination;

    protected $rules = [
        'statusFilter' => 'in:all,open,acknowledged,closed',
        'severityFilter' => 'in:all,critical,high,medium,low',
        'projectFilter' => 'regex:/^(all|\d+)$/',
        'ruleFilter' => 'nullable|string|max:100',
        'search' => 'nullable|string|max:255',
        'dismissReason' => 'nullable|string|max:500',
        'selectedIds' => 'array',
        'selectedIds.*' => 'integer|exists:risks,id',
    ];

    /** @var string 筛选：all / open / acknowledged / closed */
    public string $statusFilter = 'open';

    /** @var string 严重程度筛选 */
    public string $severityFilter = 'all';

    /** @var string 项目筛选 */
    public string $projectFilter = 'all';

    /** @var string 规则类型筛选 */
    public string $ruleFilter = 'all';

    /** @var string 搜索关键词 */
    public string $search = '';

    /** @var array 风险统计 */
    public array $stats = [];

    /** @var array 可用规则类型 */
    public array $ruleOptions = [];

    /** @var int|null 正在确认的风险 ID */
    public ?int $acknowledgingId = null;

    /** @var int|null 正在应用建议的风险 ID */
    public ?int $applyingId = null;

    /** @var bool 批量操作模式 */
    public bool $bulkMode = false;

    /** @var array 选中的风险 ID */
    public array $selectedIds = [];

    /** @var string 忽略原因 */
    public string $dismissReason = '';

    /** @var int|null 显示忽略对话框的风险 ID */
    public ?int $dismissingId = null;

    protected RiskSuggestionService $suggestionService;

    public function boot(RiskSuggestionService $suggestionService): void
    {
        $this->suggestionService = $suggestionService;
    }

    public function mount(): void
    {
        $this->loadStats();
        $this->ruleOptions = collect(Risk::select('rule_key')->distinct()->pluck('rule_key'))->toArray();
    }

    public function loadStats(): void
    {
        $total = Risk::count();
        $open = Risk::where('status', 'open')->count();
        $acknowledged = Risk::where('status', 'acknowledged')->count();
        $closed = Risk::where('status', 'closed')->count();

        $critical = Risk::where('status', 'open')->where('severity', 'critical')->count();
        $high = Risk::where('status', 'open')->where('severity', 'high')->count();

        $withSuggestion = Risk::where('status', 'open')->whereNotNull('suggestion')->count();
        $noSuggestion = Risk::where('status', 'open')->whereNull('suggestion')->count();

        $this->stats = compact(
            'total', 'open', 'acknowledged', 'closed',
            'critical', 'high', 'withSuggestion', 'noSuggestion'
        );
    }

    /**
     * 为所有开放风险生成建议
     */
    public function generateAllSuggestions(): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $result = $this->suggestionService->generateAll();
        $this->loadStats();
        session()->flash('suggestion_result', "建议生成完成：{$result['generated']} 条新增，{$result['skipped']} 条失败");
    }

    /**
     * 为单条风险生成建议
     */
    public function generateSuggestion(int $riskId): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $risk = Risk::findOrFail($riskId);
        $this->suggestionService->generate($risk);
        $this->loadStats();
    }

    /**
     * 确认风险（标记已读）
     */
    public function acknowledge(int $riskId): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $risk = Risk::findOrFail($riskId);
        $this->suggestionService->acknowledge($risk);
        $this->acknowledgingId = null;
        $this->loadStats();
    }

    /**
     * 应用建议并标记已处理
     */
    public function applySuggestion(int $riskId): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $risk = Risk::findOrFail($riskId);
        $this->suggestionService->applySuggestion($risk);
        $this->applyingId = null;
        $this->loadStats();
    }

    /**
     * 打开忽略对话框
     */
    public function confirmDismiss(int $riskId): void
    {
        $this->dismissingId = $riskId;
        $this->dismissReason = '';
    }

    /**
     * 忽略风险
     */
    public function dismiss(): void
    {
        $this->authorizeResourceAccess('manage_risks');

        if (! $this->dismissingId) {
            return;
        }
        $this->validate(['dismissReason' => 'nullable|string|max:500']);

        $risk = Risk::findOrFail($this->dismissingId);
        $this->suggestionService->dismiss($risk, $this->dismissReason ?: null);
        $this->dismissingId = null;
        $this->dismissReason = '';
        $this->loadStats();
    }

    /**
     * 取消忽略
     */
    public function cancelDismiss(): void
    {
        $this->dismissingId = null;
        $this->dismissReason = '';
    }

    /**
     * 重新打开已关闭的风险
     */
    public function reopen(int $riskId): void
    {
        $this->authorizeResourceAccess('manage_risks');
        Risk::where('id', $riskId)->update([
            'status' => 'open',
            'closed_at' => null,
            'acknowledged_at' => null,
        ]);
        $this->loadStats();
    }

    /**
     * 批量生成建议
     */
    public function bulkGenerateSuggestions(): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $this->validate([
            'selectedIds' => 'required|array',
            'selectedIds.*' => 'integer|exists:risks,id',
        ]);
        $risks = Risk::whereIn('id', $this->selectedIds)->whereNull('suggestion')->get();
        $count = 0;
        foreach ($risks as $risk) {
            $this->suggestionService->generate($risk);
            $count++;
        }
        $this->selectedIds = [];
        $this->bulkMode = false;
        $this->loadStats();
        session()->flash('suggestion_result', "已为 {$count} 条风险生成建议");
    }

    /**
     * 批量确认
     */
    public function bulkAcknowledge(): void
    {
        $this->authorizeResourceAccess('manage_risks');
        $this->validate([
            'selectedIds' => 'required|array',
            'selectedIds.*' => 'integer|exists:risks,id',
        ]);
        Risk::whereIn('id', $this->selectedIds)
            ->where('status', 'open')
            ->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        $this->selectedIds = [];
        $this->bulkMode = false;
        $this->loadStats();
    }

    /**
     * 切换风险选中状态
     */
    public function toggleSelect(int $riskId): void
    {
        if (in_array($riskId, $this->selectedIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$riskId]));
        } else {
            $this->selectedIds[] = $riskId;
        }
    }

    /**
     * 获取筛选后的风险查询
     */
    protected function getQuery()
    {
        $query = Risk::with('project:id,name');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->severityFilter !== 'all') {
            $query->where('severity', $this->severityFilter);
        }

        if ($this->projectFilter !== 'all') {
            $query->where('project_id', $this->projectFilter);
        }

        if ($this->ruleFilter !== 'all') {
            $query->where('rule_key', $this->ruleFilter);
        }

        if (trim($this->search) !== '') {
            $search = trim($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%");
            });
        }

        return $query->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END")
            ->orderBy('last_seen_at', 'desc');
    }

    public function render()
    {
        $projects = Project::select('id', 'name')->orderBy('name')->get();
        $risks = $this->getQuery()->paginate(20);
        $openRisks = Risk::where('status', 'open')->count();

        return view('livewire.risk-manager', [
            'risks' => $risks,
            'projects' => $projects,
            'openRisks' => $openRisks,
        ])->layout('layouts.app')->title('风险中心 — Project Monitor');
    }
}
