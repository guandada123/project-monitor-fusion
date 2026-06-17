<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'source', 'external_id', 'event_type', 'occurred_at',
        'payload', 'fingerprint', 'processed_at', 'project_id',
        'error', 'retry_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeFailed($query)
    {
        return $query->whereNotNull('error');
    }
}
