<?php

namespace Modules\ChatBot\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
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
