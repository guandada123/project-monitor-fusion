<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlowQuery extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'query',
        'bindings',
        'time',
        'connection_name',
        'caller',
        'request_uri',
        'request_method',
        'user_id',
        'is_slow',
        'is_critical',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'time' => 'float',
            'bindings' => 'array',
            'is_slow' => 'boolean',
            'is_critical' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    // ── Scopes ──

    public function scopeSlow(Builder $q): Builder
    {
        return $q->where('is_slow', true);
    }

    public function scopeCritical(Builder $q): Builder
    {
        return $q->where('is_critical', true);
    }

    public function scopeRecent(Builder $q, int $hours = 24): Builder
    {
        return $q->where('captured_at', '>=', now()->subHours($hours));
    }

    public function scopeByConnection(Builder $q, string $name): Builder
    {
        return $q->where('connection_name', $name);
    }

    // ── Accessors ──

    public function getDurationLabelAttribute(): string
    {
        return $this->time >= 1000
            ? sprintf('%.2fs', $this->time / 1000)
            : sprintf('%dms', (int) $this->time);
    }

    public function getQueryPreviewAttribute(): string
    {
        $maxLen = 200;

        return mb_strlen($this->query) > $maxLen
            ? mb_substr($this->query, 0, $maxLen).'...'
            : $this->query;
    }

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
