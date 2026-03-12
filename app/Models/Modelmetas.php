<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modelmetas extends Model
{
    use HasFactory;

    protected $table = 'model_metas';

    protected $fillable = [
        'metable_type',
        'metable_id',
        'meta_group',
        'meta_key',
        'meta_value',
        'version',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
