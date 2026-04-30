<?php

namespace App\Core\Pages\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'entity_type',
    'entity_id',
    'field_key',
    'value',
])]
class AdditionalFieldValue extends Model
{
}
