<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Metric extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'key', 'name', 'kind', 'definition',
        'dimensions', 'filters', 'schedule_cron',
        'last_computed_at', 'current_value', 'current_json_value',
    ];

    protected $casts = [
        'definition' => 'array',
        'dimensions' => 'array',
        'filters' => 'array',
        'current_json_value' => 'array',
        'last_computed_at' => 'datetime',
        'current_value' => 'decimal:4',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MetricSnapshot::class);
    }

    /**
     * 最新快照
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(MetricSnapshot::class)->latestOfMany('captured_at');
    }

    public function scopeSystem($query)
    {
        return $query->where('kind', 'system');
    }

    public function scopeCustom($query)
    {
        return $query->where('kind', 'custom');
    }
}
