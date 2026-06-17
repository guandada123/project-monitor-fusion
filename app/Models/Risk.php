<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Project|null $project
 * @property-read \Carbon\Carbon|null $first_seen_at
 * @property-read \Carbon\Carbon|null $last_seen_at
 */
class Risk extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'severity', 'rule_key', 'title', 'detail',
        'evidence', 'status', 'first_seen_at', 'last_seen_at',
        'acknowledged_at', 'closed_at', 'occurrence_count', 'meta',
        'suggestion', 'suggestion_data', 'suggestion_applied_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'meta' => 'array',
        'suggestion_data' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeByRule($query, string $ruleKey)
    {
        return $query->where('rule_key', $ruleKey);
    }
}
