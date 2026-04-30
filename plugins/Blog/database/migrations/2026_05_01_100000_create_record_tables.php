<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('has_categories')->default(true);
            $table->boolean('has_tags')->default(true);
            $table->boolean('has_seo')->default(true);
            $table->boolean('has_featured_image')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('record_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_type_id')->constrained('record_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('record_categories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['record_type_id', 'slug']);
            $table->index(['record_type_id', 'name']);
        });

        Schema::create('records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_type_id')->constrained('record_types')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('record_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable();
            $table->string('excerpt')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['record_type_id', 'slug']);
            $table->index(['record_type_id', 'status', 'published_at']);
        });

        Schema::create('record_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_type_id')->constrained('record_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['record_type_id', 'slug']);
            $table->index(['record_type_id', 'name']);
        });

        Schema::create('record_record_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')->constrained('records')->cascadeOnDelete();
            $table->foreignId('record_tag_id')->constrained('record_tags')->cascadeOnDelete();
            $table->unique(['record_id', 'record_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_record_tag');
        Schema::dropIfExists('record_tags');
        Schema::dropIfExists('record_categories');
        Schema::dropIfExists('records');
        Schema::dropIfExists('record_types');
    }
};
