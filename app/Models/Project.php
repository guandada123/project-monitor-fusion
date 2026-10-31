<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read float  $completion_rate
 * @property-read float  $overdue_rate
 * @property-read int    $open_risk_count
 * @property-read int    $todo_count
 * @property-read int    $done_count
 * @property-read int    $in_progress_count
 * @property-read Carbon|null $start_at
 * @property-read Carbon|null $end_at
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const COMPLETED_STATUSES = ['done', 'closed', 'completed'];

    protected $fillable = [
        'name', 'slug', 'source', 'external_id', 'owner_id',
        'status', 'description', 'start_at', 'end_at',
        'meta', 'tags', 'config',
    ];

    protected $casts = [
        'meta' => 'array',
        'tags' => 'array',
        'config' => 'array',
        'start_at' => 'date',
        'end_at' => 'date',
    ];

    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // ── 完成率计算（由 pm-dash 融合引入） ─────────────────
    public function completionRate(): float
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->tasks()->whereIn('status', static::COMPLETED_STATUSES)->count();

        return round(($done / $total) * 100, 2);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // Computed — 所有 Accessor 优先使用预加载集合 / withCount 预计算值，避免 N+1
    public function getCompletionRateAttribute(): float
    {
        // 快速路径：调用方用了 withCount(['tasks', 'tasks as done_tasks_count'])
        // 时零查询。两个计数必须同时存在才采用，否则会静默算出错误比率。
        if (isset($this->attributes['tasks_count'], $this->attributes['done_tasks_count'])) {
            $total = (int) $this->attributes['tasks_count'];
            if ($total === 0) {
                return 0;
            }

            return round(((int) $this->attributes['done_tasks_count'] / $total) * 100, 2);
        }

        if ($this->relationLoaded('tasks')) {
            $total = $this->tasks->count();
            $done = $this->tasks->whereIn('status', static::COMPLETED_STATUSES)->count();
        } else {
            return $this->completionRate();
        }
        if ($total === 0) {
            return 0;
        }

        return round(($done / $total) * 100, 2);
    }

    /**
     * 是否有任务数据 —— 决定视图该显示「完成率」还是「代码规模」。
     *
     * pmf 自己的 tasks 表从来没有数据（它是项目监控台，不是任务跟踪工具），
     * 于是 completion_rate 恒为 0。**那是「没有数据源」，不是「进度为零」。**
     * 视图若不先判断，就会把「没数据」渲染成「0%」，让人误以为项目毫无进展。
     * 这类「假 0」比空着更糟：空着你会去查，0% 你会信。
     */
    public function getHasTaskDataAttribute(): bool
    {
        if (isset($this->attributes['tasks_count'])) {
            return (int) $this->attributes['tasks_count'] > 0;
        }

        return $this->relationLoaded('tasks') ? $this->tasks->isNotEmpty() : false;
    }

    /** 采集到的代码总行数（0 = 未采集）。 */
    public function getCodeLocAttribute(): int
    {
        return (int) (is_array($this->meta) ? ($this->meta['code']['total_loc'] ?? 0) : 0);
    }

    /** 近 7 天提交数（0 = 未采集）。 */
    public function getCommits7dAttribute(): int
    {
        return (int) (is_array($this->meta) ? ($this->meta['commits_7d'] ?? 0) : 0);
    }

    public function getOverdueRateAttribute(): float
    {
        if ($this->relationLoaded('tasks')) {
            /** @var Collection<int, Task> $notDone */
            $notDone = $this->tasks->where('status', '!=', 'done');
            $total = $notDone->count();
            if ($total === 0) {
                return 0;
            }
            $today = now()->toDateString();
            $overdue = $notDone->filter(fn (Task $t) => $t->due_at && $t->due_at->toDateString() < $today)->count();

            return round(($overdue / $total) * 100, 1);
        }
        $total = $this->tasks()->where('status', '!=', 'done')->count();
        if ($total === 0) {
            return 0;
        }
        $overdue = $this->tasks()
            ->where('status', '!=', 'done')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->count();

        return round(($overdue / $total) * 100, 1);
    }

    public function getOpenRiskCountAttribute(): int
    {
        if ($this->relationLoaded('risks')) {
            return $this->risks->where('status', 'open')->count();
        }

        return $this->risks()->where('status', 'open')->count();
    }
}
