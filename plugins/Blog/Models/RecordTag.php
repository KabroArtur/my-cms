<?php

namespace Plugins\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecordTag extends Model
{
    protected $table = 'record_tags';

    protected $fillable = [
        'record_type_id',
        'name',
        'slug',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(RecordType::class, 'record_type_id');
    }

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(Record::class, 'record_record_tag', 'record_tag_id', 'record_id');
    }
}
