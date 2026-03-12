<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Revision.
 *
 * Base model to allow for revision history on
 * any model that extends this model
 */
class Revision extends Eloquent
{
    use HasFactory;

    /**
     * @var string
     */
    public $table = 'revisions';

    protected $guarded = ['id'];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisionData(): HasMany
    {
        return $this->hasMany(RevisionData::class, 'revision_id');
    }
}
