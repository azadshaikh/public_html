<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settings extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'settings';

    protected $primaryKey = 'id';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get setting by group and key
     */
    public static function getByGroupAndKey(string $group, string $key): ?self
    {
        return self::query()->where('group', $group)->where('key', $key)->first();
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return self::query()->where('group', $group)->pluck('value', 'key')->toArray();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the cast value based on type
     * For booleans, always return true/false boolean values for use in PHP
     */
    protected function getCastValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => in_array($this->value, ['true', '1', 1, true], true),
            'json' => json_decode($this->value, true),
            'array' => json_decode($this->value, true) ?? [],
            'float' => (float) $this->value,
            default => (string) $this->value,
        };
    }

    /**
     * Get the raw value as string for form display
     * For booleans, always return 'true' or 'false' strings
     */
    protected function getRawValueAttribute(): string
    {
        if ($this->type === 'boolean') {
            return in_array($this->value, ['true', '1', 1, true], true) ? 'true' : 'false';
        }

        return (string) $this->value;
    }

    /**
     * Set the value with proper casting for storage
     * For booleans, always store as 'true' or 'false' strings
     */
    protected function setValueAttribute($value): void
    {
        if ($this->type === 'json' || $this->type === 'array') {
            $this->attributes['value'] = is_array($value) ? json_encode($value) : $value;
        } elseif ($this->type === 'boolean') {
            // Always store booleans as 'true' or 'false' strings for consistency
            if (is_string($value)) {
                $this->attributes['value'] = in_array($value, ['true', '1', 'yes', 'on'], true) ? 'true' : 'false';
            } else {
                $this->attributes['value'] = $value ? 'true' : 'false';
            }
        } else {
            $this->attributes['value'] = (string) $value;
        }
    }
}
