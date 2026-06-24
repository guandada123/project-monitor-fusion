<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read bool          $is_overdue
 * @property-read User|null     $assignee
 * @property-read Collection<int,User> $assignees
 * @property-read Carbon|null $due_at
 * @property-read Project|null  $project
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'external_id', 'title', 'description',
        'assignee_id', 'status', 'priority',
        'estimate', 'spent', 'start_at', 'due_at', 'completed_at',
        'tags', 'meta',
    ];

    protected $casts = [
        'estimate' => 'decimal:2',
        'spent' => 'decimal:2',
        'start_at' => 'date',
        'due_at' => 'date',
        'completed_at' => 'datetime',
        'tags' => 'array',
        'meta' => 'array',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Users assigned to this task (via assignments pivot).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignments')
            ->withPivot('role', 'allocation_percent')
            ->withTimestamps();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereNotIn('status', ['done']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'done')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString());
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCritical($query)
    {
        return $query->where('priority', 'critical');
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['critical', 'high']);
    }

    // Helpers
    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'done'
            && $this->due_at
            && $this->due_at->toDateString() < now()->toDateString();
    }
}
