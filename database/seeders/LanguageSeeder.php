<?php

namespace Database\Seeders;

use App\Core\Languages\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            [
                'name' => 'Ukrainian',
                'native_name' => 'Українська',
                'code' => 'uk',
                'locale' => 'uk_UA',
                'direction' => 'ltr',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'English',
                'native_name' => 'English',
                'code' => 'en',
                'locale' => 'en_US',
                'direction' => 'ltr',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Russian',
                'native_name' => 'Русский',
                'code' => 'ru',
                'locale' => 'ru_RU',
                'direction' => 'ltr',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];

        foreach ($languages as $attributes) {
            Language::query()->updateOrCreate(
                ['code' => $attributes['code']],
                $attributes,
            );
        }
    }
}