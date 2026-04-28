<?php

namespace Database\Seeders;

use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminUser = User::query()->firstOrNew([
            'username' => 'admin',
        ]);

        $adminUser->name = 'Administrator';
        $adminUser->email = $adminUser->email ?: 'admin@example.com';

        if (! $adminUser->exists) {
            $adminUser->password = env('ADMIN_INITIAL_PASSWORD', Str::password(24));
            $adminUser->two_factor_channel = 'email';
            $adminUser->two_factor_enabled_at = now();
        }

        $adminUser->save();

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
