<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['metric_id', 'captured_at', 'value', 'json_value'];

    protected $casts = [
        'captured_at' => 'datetime',
        'value' => 'decimal:4',
        'json_value' => 'array',
    ];

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
