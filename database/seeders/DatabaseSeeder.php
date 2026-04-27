<?php

namespace Database\Seeders;

use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
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

        Page::query()->firstOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Главная',
                'status' => PageStatus::Published->value,
                'visibility' => PageVisibility::Public->value,
                'excerpt' => 'Стартовая страница сайта создается автоматически вместе с административной зоной.',
                'content' => '<p>Главная страница сайта уже готова. Дальше ее можно редактировать из административной панели.</p>',
                'template' => 'default',
                'meta_title' => 'Главная',
                'meta_description' => 'Главная страница сайта.',
                'sort_order' => 0,
                'is_home' => true,
                'published_at' => now(),
            ],
        );

        Page::query()
            ->where('slug', '!=', 'home')
            ->where('is_home', true)
            ->update(['is_home' => false]);
    }
}
