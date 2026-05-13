<?php

namespace App\Core\Languages\Models;

use App\Core\Pages\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'name',
        'native_name',
        'code',
        'locale',
        'direction',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('native_name');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}