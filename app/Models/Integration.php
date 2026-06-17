<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'name', 'config', 'status',
        'last_sync_at', 'sync_state', 'error_message',
    ];

    protected $casts = [
        'config' => 'array',
        'sync_state' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
