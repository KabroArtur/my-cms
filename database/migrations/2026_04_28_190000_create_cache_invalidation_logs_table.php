<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_invalidation_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20);
            $table->string('reason', 120)->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scope', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_invalidation_logs');
    }
};
