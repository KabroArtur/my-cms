<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Миграция создает таблицу одноразовых challenge второго фактора.
     */
    public function up(): void
    {
        Schema::create('two_factor_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Миграция удаляет таблицу challenge второго фактора.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_challenges');
    }
};