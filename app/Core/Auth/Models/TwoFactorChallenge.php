<?php

namespace App\Core\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель хранит одноразовый challenge для второго фактора.
 * Она дает базу для email-кодов сейчас и для других каналов позже.
 */
#[Fillable([
    'user_id',
    'channel',
    'code_hash',
    'expires_at',
    'completed_at',
    'last_sent_at',
])]
class TwoFactorChallenge extends Model
{
    /**
     * Challenge приводит даты к объектам времени.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * Challenge принадлежит пользователю.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope оставляет только активные и неистекшие challenge.
     */
    public function scopeActive(Builder $query): void
    {
        $query
            ->whereNull('completed_at')
            ->where('expires_at', '>', now());
    }
}