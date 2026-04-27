<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->updateOrCreate([
            'username' => 'admin',
        ], [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => 'admin',
            'two_factor_channel' => 'email',
            'two_factor_enabled_at' => now(),
        ]);

        $this->call(AccessSeeder::class);
    }
}
