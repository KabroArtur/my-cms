<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_field_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->json('location_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('additional_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('additional_field_groups')->cascadeOnDelete();
            $table->string('label');
            $table->string('key')->unique();
            $table->string('type', 40);
            $table->json('settings')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group_id', 'sort_order']);
        });

        Schema::create('additional_field_values', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('field_key');
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'field_key'], 'additional_field_values_entity_field_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_field_values');
        Schema::dropIfExists('additional_fields');
        Schema::dropIfExists('additional_field_groups');
    }
};
