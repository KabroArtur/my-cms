<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('seo_noindex')
                ->default(false)
                ->after('meta_description');

            $table->boolean('seo_nofollow')
                ->default(false)
                ->after('seo_noindex');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['seo_noindex', 'seo_nofollow']);
        });
    }
};