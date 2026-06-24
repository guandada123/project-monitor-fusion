<?php

namespace App\Livewire;

use App\Exports\ExportEngine;
use App\Models\ExportHistory;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ExportPanel extends Component
{
    /** @var bool 是否显示导出面板 */
    public bool $showPanel = false;

    /** @var string 当前步骤: select, progress, done */
    public string $step = 'select';

    /** @var string 导出实体类型 */
    public string $entityType = 'project';

    /** @var string 导出格式 */
    public string $format = 'xlsx';

    /** @var array 可用实体类型 */
    public array $entityTypes = [];

    /** @var array 当前实体类型的可用列 */
    public array $availableColumns = [];

    /** @var array 选中的列 (key => 勾选) */
    public array $selectedColumns = [];

    /** @var array 当前导出进度 */
    public array $exportProgress = [];

    /** @var array 导出历史 */
    public array $history = [];

    /** @var string|null 错误消息 */
    public ?string $errorMessage = null;

    protected ExportEngine $exportEngine;

    public function boot(ExportEngine $exportEngine): void
    {
        $this->exportEngine = $exportEngine;
    }

    public function mount(): void
    {
        $this->entityTypes = $this->exportEngine->getEntityTypes();
        $this->loadColumns();
        $this->loadHistory();
    }

    /**
     * 切换实体类型时更新列
     */
    public function updatedEntityType(): void
    {
        $this->loadColumns();
    }

    // Listen for event-driven panel opening from child pages
    protected $listeners = ['open-export-panel'];

    /**
     * 打开导出面板
     */
    public function openPanel(?string $entityType = null): void
    {
        if ($entityType && in_array($entityType, ['project', 'task', 'risk'])) {
            $this->entityType = $entityType;
            $this->loadColumns();
        }
        $this->step = 'select';
        $this->errorMessage = null;
        $this->showPanel = true;
    }

    /**
     * 关闭导出面板
     */
    public function closePanel(): void
    {
        $this->showPanel = false;
        $this->step = 'select';
        $this->errorMessage = null;
    }

    /**
     * 加载当前实体类型的列配置
     */
    protected function loadColumns(): void
    {
        $this->availableColumns = $this->exportEngine->getColumnsFor($this->entityType);
        $this->selectedColumns = [];
        foreach ($this->availableColumns as $key => $label) {
            // 默认选中前8列
            $this->selectedColumns[$key] = count($this->selectedColumns) < 8;
        }
    }

    /**
     * 加载导出历史
     */
    protected function loadHistory(): void
    {
        $this->history = $this->exportEngine->getHistory(10)
            ->toArray();
    }

    /**
     * 执行导出
     */
    public function startExport(): void
    {
        $this->validate([
            'entityType' => 'required|in:project,task,risk',
            'format' => 'required|in:csv,xlsx,pdf',
        ]);

        // Get selected columns
        $columns = [];
        foreach ($this->selectedColumns as $key => $selected) {
            if ($selected && isset($this->availableColumns[$key])) {
                $columns[$key] = $this->availableColumns[$key];
            }
        }

        if (empty($columns)) {
            $this->errorMessage = '请至少选择一列进行导出';

            return;
        }

        $this->step = 'progress';
        $this->errorMessage = null;

        try {
            $history = $this->exportEngine->export(
                $this->entityType,
                $this->format,
                [], // filters empty by default
                $columns,
            );

            $this->exportProgress = $history->toArray();
            $this->step = 'done';
            $this->loadHistory();

            // Flash success for toast
            session()->flash('message', "导出完成：共 {$history->record_count} 条记录");
        } catch (\Exception $e) {
            $this->errorMessage = '导出失败：'.$e->getMessage();
            $this->step = 'select';
        }
    }

    /**
     * 下载导出文件
     */
    public function download(int $historyId): void
    {
        $history = ExportHistory::findOrFail($historyId);

        if (! $history->file_path || ! Storage::disk('local')->exists($history->file_path)) {
            $this->errorMessage = '导出文件不存在或已被清理';

            return;
        }

        $this->dispatch('download-export', url: Storage::disk('local')->url($history->file_path));
    }

    /**
     * 重新开始
     */
    public function resetExport(): void
    {
        $this->step = 'select';
        $this->errorMessage = null;
        $this->exportProgress = [];
        $this->format = 'xlsx';
        $this->loadColumns();
    }

    /**
     * 全选/取消全选列
     */
    public function toggleAllColumns(bool $selectAll): void
    {
        foreach ($this->selectedColumns as $key => $selected) {
            $this->selectedColumns[$key] = $selectAll;
        }
    }

    public function render()
    {
        return view('livewire.export-panel');
    }
}
