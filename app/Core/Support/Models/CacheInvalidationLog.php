<?php

namespace App\Core\Support\Models;

use Illuminate\Database\Eloquent\Model;

class CacheInvalidationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'scope',
        'reason',
        'triggered_by',
        'created_at',
    ];

    protected $casts = [
        'triggered_by' => 'integer',
        'created_at' => 'datetime',
    ];
}
