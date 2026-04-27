<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Таблица получает явное поле видимости страницы.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('visibility')->default('public')->after('status');
            $table->index('visibility');
        });
    }

    /**
     * Поле видимости удаляется при откате миграции.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};