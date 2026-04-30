<?php

namespace Plugins\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordType extends Model
{
    protected $table = 'record_types';

    protected $fillable = [
        'name',
        'slug',
        'has_categories',
        'has_tags',
        'has_seo',
        'has_featured_image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'has_categories' => 'boolean',
            'has_tags' => 'boolean',
            'has_seo' => 'boolean',
            'has_featured_image' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'record_type_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(RecordCategory::class, 'record_type_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(RecordTag::class, 'record_type_id');
    }
}
