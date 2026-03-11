<?php

namespace Modules\ChatBot\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $purpose
 * @property string $model
 * @property string $tone
 * @property string $system_prompt
 * @property string|null $notes
 * @property string $status
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PromptTemplate extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'active' => 'Active',
        'retired' => 'Retired',
    ];

    public const TONES = [
        'supportive' => 'Supportive',
        'professional' => 'Professional',
        'friendly' => 'Friendly',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'purpose',
        'model',
        'tone',
        'system_prompt',
        'notes',
        'status',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public static function defaultFormData(): array
    {
        return [
            'name' => '',
            'slug' => '',
            'purpose' => '',
            'model' => 'gpt-4.1-mini',
            'tone' => 'supportive',
            'system_prompt' => '',
            'notes' => '',
            'status' => 'draft',
            'is_default' => false,
        ];
    }
}
