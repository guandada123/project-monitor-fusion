<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportHistory extends Model
{
    use HasFactory;

    protected $table = 'export_history';

    protected $fillable = [
        'user_id',
        'entity_type',
        'format',
        'filters',
        'selected_columns',
        'record_count',
        'file_path',
        'file_size',
        'status',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'selected_columns' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function getEntityLabelAttribute(): string
    {
        return match ($this->entity_type) {
            'project' => '项目',
            'task' => '任务',
            'risk' => '风险',
            default => $this->entity_type,
        };
    }

    public function getFormatLabelAttribute(): string
    {
        return strtoupper($this->format);
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->completed_at) return null;
        return $this->created_at->diffForHumans($this->completed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE);
    }
}
