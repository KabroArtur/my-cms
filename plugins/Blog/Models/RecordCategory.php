<?php

namespace Plugins\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordCategory extends Model
{
    protected $table = 'record_categories';

    protected $fillable = [
        'record_type_id',
        'name',
        'slug',
        'parent_id',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(RecordType::class, 'record_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
