<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPostTerm extends Model
{
    use HasFactory;

    protected $table = 'cms_post_terms';

    protected $fillable = [
        'term_type',
        'post_id',
        'term_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'post_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'term_id');
    }

    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'term_id' => 'integer',
        ];
    }
}
