<?php

use App\Core\Pages\Enums\PageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Таблица создает базовое хранилище страниц CMS.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default(PageStatus::Draft->value);
            $table->string('template')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_home')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['parent_id', 'sort_order']);
        });
    }

    /**
     * Таблица удаляется целиком при откате миграции.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};