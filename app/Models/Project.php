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
        $done = $this->tasks()->whereIn('status', ['done', 'closed', 'completed'])->count();

        return round(($done / $total) * 100, 1);
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

    // Computed — 所有 Accessor 优先使用预加载集合，避免 N+1
    public function getCompletionRateAttribute(): float
    {
        if ($this->relationLoaded('tasks')) {
            $total = $this->tasks->count();
            if ($total === 0) {
                return 0;
            }
            $done = $this->tasks->where('status', 'done')->count();

            return round(($done / $total) * 100, 1);
        }
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->tasks()->whereIn('status', ['done'])->count();

        return round(($done / $total) * 100, 1);
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
