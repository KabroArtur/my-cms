<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->after('parent_id')->constrained('languages')->restrictOnDelete();
            $table->uuid('translation_group_id')->nullable()->after('language_id');
        });

        $defaultLanguageId = DB::table('languages')->where('is_default', true)->value('id');

        DB::table('pages')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $page) use ($defaultLanguageId): void {
                DB::table('pages')
                    ->where('id', $page->id)
                    ->update([
                        'language_id' => $defaultLanguageId,
                        'translation_group_id' => (string) Str::uuid(),
                    ]);
            });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable(false)->change();
            $table->uuid('translation_group_id')->nullable(false)->change();
            $table->dropUnique('pages_slug_unique');
            $table->unique(['language_id', 'slug']);
            $table->index(['language_id', 'translation_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['language_id', 'translation_group_id']);
            $table->dropUnique(['language_id', 'slug']);
            $table->dropConstrainedForeignId('language_id');
            $table->dropColumn('translation_group_id');
            $table->unique('slug');
        });
    }
};