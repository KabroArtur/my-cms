<?php

namespace App\Core\Pages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'key',
    'description',
    'location_rules',
    'is_active',
    'sort_order',
])]
class AdditionalFieldGroup extends Model
{
    protected function casts(): array
    {
        return [
            'location_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AdditionalField::class, 'group_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
