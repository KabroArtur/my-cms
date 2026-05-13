<?php

namespace Database\Seeders;

use App\Core\Languages\Models\Language;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $adminUser->password = env('ADMIN_INITIAL_PASSWORD', 'admin');
            $adminUser->two_factor_channel = 'email';
            $adminUser->two_factor_enabled_at = now();
        }

        $adminUser->save();

        $this->call(LanguageSeeder::class);

        $this->call(AccessSeeder::class);

        $defaultLanguageId = Language::query()->where('is_default', true)->value('id');

        Page::query()->firstOrCreate(
            ['slug' => 'home', 'language_id' => $defaultLanguageId],
            [
                'language_id' => $defaultLanguageId,
                'translation_group_id' => (string) Str::uuid(),
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
            ->where('language_id', $defaultLanguageId)
            ->update(['is_home' => false]);

        $this->seedThemePostsDemo();
        $this->seedBlogDemo();
        $this->seedRecordsDemo();
    }

    private function seedThemePostsDemo(): void
    {
        if (! Schema::hasTable('theme_post_categories') || ! Schema::hasTable('theme_posts')) {
            return;
        }

        if (DB::table('theme_posts')->exists()) {
            return;
        }

        $newsCategoryId = DB::table('theme_post_categories')->insertGetId([
            'name' => 'Новости CMS',
            'slug' => 'cms-news',
            'description' => 'Технические обновления и изменения платформы.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guidesCategoryId = DB::table('theme_post_categories')->insertGetId([
            'name' => 'Гайды',
            'slug' => 'guides',
            'description' => 'Быстрые инструкции для редакторов и администраторов.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('theme_posts')->insert([
            [
                'category_id' => $newsCategoryId,
                'title' => 'Запущен модуль плагинов',
                'slug' => 'plugins-module-live',
                'excerpt' => 'Теперь плагины можно устанавливать, включать и отключать из админки.',
                'content' => 'Базовый lifecycle плагинов подключен: install, enable, disable, delete.',
                'is_published' => true,
                'published_at' => now()->subDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $guidesCategoryId,
                'title' => 'Как добавить новый плагин',
                'slug' => 'how-to-create-plugin',
                'excerpt' => 'Краткий чеклист структуры каталога plugins/*/plugin.json и маршрутов.',
                'content' => 'Создайте plugin.json, provider, routes и при необходимости миграции.',
                'is_published' => true,
                'published_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $newsCategoryId,
                'title' => 'Макросы posts() и categories() активны',
                'slug' => 'theme-posts-macros-enabled',
                'excerpt' => 'Тема теперь может выводить записи без отдельного шаблонного SQL.',
                'content' => 'Используйте posts([\'limit\' => 3]) и categories() прямо в Blade.',
                'is_published' => true,
                'published_at' => now()->subHours(6),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedBlogDemo(): void
    {
        if (
            ! Schema::hasTable('blog_categories')
            || ! Schema::hasTable('blog_tags')
            || ! Schema::hasTable('blog_posts')
            || ! Schema::hasTable('blog_post_tag')
        ) {
            return;
        }

        if (DB::table('blog_posts')->exists()) {
            return;
        }

        $authorId = User::query()->where('username', 'admin')->value('id');

        $productCategoryId = DB::table('blog_categories')->insertGetId([
            'name' => 'Продукт',
            'slug' => 'product',
            'description' => 'Обновления функциональности CMS.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tipsCategoryId = DB::table('blog_categories')->insertGetId([
            'name' => 'Практика',
            'slug' => 'practice',
            'description' => 'Советы по работе редактора и админа.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $releaseTagId = DB::table('blog_tags')->insertGetId([
            'name' => 'release',
            'slug' => 'release',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guideTagId = DB::table('blog_tags')->insertGetId([
            'name' => 'guide',
            'slug' => 'guide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstPostId = DB::table('blog_posts')->insertGetId([
            'category_id' => $productCategoryId,
            'author_id' => $authorId,
            'title' => 'Blog-плагин в админке через выпадающее меню',
            'slug' => 'blog-plugin-sidebar-group',
            'excerpt' => 'Пункт Blog теперь содержит вложенные разделы: записи, категории, теги.',
            'content' => 'Структура меню плагина стала групповой: один узел Blog и дочерние экраны управления контентом.',
            'is_published' => true,
            'published_at' => now()->subHours(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondPostId = DB::table('blog_posts')->insertGetId([
            'category_id' => $tipsCategoryId,
            'author_id' => $authorId,
            'title' => 'Как редактору оформить первую публикацию',
            'slug' => 'first-post-editor-guide',
            'excerpt' => 'Короткий гайд по заголовку, excerpt, тегам и публикации.',
            'content' => 'Заполните title, slug, excerpt, выберите категорию и теги, затем включите публикацию.',
            'is_published' => true,
            'published_at' => now()->subHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('blog_post_tag')->insert([
            ['post_id' => $firstPostId, 'tag_id' => $releaseTagId],
            ['post_id' => $secondPostId, 'tag_id' => $guideTagId],
            ['post_id' => $secondPostId, 'tag_id' => $releaseTagId],
        ]);
    }

    private function seedRecordsDemo(): void
    {
        if (
            ! Schema::hasTable('record_types')
            || ! Schema::hasTable('records')
            || ! Schema::hasTable('record_categories')
            || ! Schema::hasTable('record_tags')
            || ! Schema::hasTable('record_record_tag')
        ) {
            return;
        }

        if (DB::table('record_types')->exists()) {
            return;
        }

        $authorId = User::query()->where('username', 'admin')->value('id');

        $blogTypeId = DB::table('record_types')->insertGetId([
            'name' => 'Blog',
            'slug' => 'blog',
            'has_categories' => true,
            'has_tags' => true,
            'has_seo' => true,
            'has_featured_image' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('record_types')->insert([
            'name' => 'News',
            'slug' => 'news',
            'has_categories' => true,
            'has_tags' => true,
            'has_seo' => true,
            'has_featured_image' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guidesCategoryId = DB::table('record_categories')->insertGetId([
            'record_type_id' => $blogTypeId,
            'name' => 'Guides',
            'slug' => 'guides',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsCategoryId = DB::table('record_categories')->insertGetId([
            'record_type_id' => $blogTypeId,
            'name' => 'News',
            'slug' => 'news',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seoTagId = DB::table('record_tags')->insertGetId([
            'record_type_id' => $blogTypeId,
            'name' => 'SEO',
            'slug' => 'seo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cmsTagId = DB::table('record_tags')->insertGetId([
            'record_type_id' => $blogTypeId,
            'name' => 'CMS',
            'slug' => 'cms',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $frontendTagId = DB::table('record_tags')->insertGetId([
            'record_type_id' => $blogTypeId,
            'name' => 'Frontend',
            'slug' => 'frontend',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstRecordId = DB::table('records')->insertGetId([
            'record_type_id' => $blogTypeId,
            'author_id' => $authorId,
            'category_id' => $guidesCategoryId,
            'title' => 'How to build CMS module architecture',
            'slug' => 'how-to-build-cms-module-architecture',
            'content' => 'Пошаговый разбор модульной архитектуры для CMS с плагинами и разделением зон ответственности.',
            'excerpt' => 'Архитектурный гайд по модулю записей и плагинам.',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondRecordId = DB::table('records')->insertGetId([
            'record_type_id' => $blogTypeId,
            'author_id' => $authorId,
            'category_id' => $newsCategoryId,
            'title' => 'Frontend guide for records workspace',
            'slug' => 'frontend-guide-for-records-workspace',
            'content' => 'Описание UX-подхода к экрану разделов, вкладкам и управлению записями/категориями/тегами.',
            'excerpt' => 'Практический гайд по админскому workspace для раздела Blog.',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('record_record_tag')->insert([
            ['record_id' => $firstRecordId, 'record_tag_id' => $cmsTagId],
            ['record_id' => $firstRecordId, 'record_tag_id' => $seoTagId],
            ['record_id' => $secondRecordId, 'record_tag_id' => $frontendTagId],
        ]);
    }
}
