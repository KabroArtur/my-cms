<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Миграция добавляет username для входа в административную систему.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        DB::table('users')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                $base = Str::slug((string) $user->name, '') ?: Str::before((string) $user->email, '@') ?: 'user';
                $username = $base;
                $suffix = 2;

                while (DB::table('users')
                    ->where('username', $username)
                    ->where('id', '!=', $user->id)
                    ->exists()) {
                    $username = $base.$suffix;
                    $suffix++;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->unique('username');
        });
    }

    /**
     * Миграция откатывает поле username у пользователей.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};