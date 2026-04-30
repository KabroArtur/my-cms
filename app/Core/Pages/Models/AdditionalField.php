<?php

namespace App\Core\Pages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_id',
    'label',
    'key',
    'type',
    'settings',
    'default_value',
    'is_required',
    'sort_order',
])]
class AdditionalField extends Model
{
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdditionalFieldGroup::class, 'group_id');
    }
}
