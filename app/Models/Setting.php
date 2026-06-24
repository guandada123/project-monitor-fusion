<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string|null $group
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Cast the stored value to boolean.
     */
    public function boolValue(): bool
    {
        return in_array($this->value, ['true', '1', 'yes'], true);
    }

    /**
     * Cast the stored value to integer.
     */
    public function intValue(): int
    {
        return (int) $this->value;
    }
}
