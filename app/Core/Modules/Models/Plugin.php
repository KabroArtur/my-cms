<?php

namespace App\Core\Modules\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'version',
        'status',
        'installed_at',
        'enabled_at',
        'disabled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
