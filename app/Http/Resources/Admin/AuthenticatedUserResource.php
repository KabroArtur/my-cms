<?php

namespace App\Http\Resources\Admin;

use App\Core\Security\Services\AdminPathManager;
use App\Core\Modules\Services\PluginManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс отдает административного пользователя в стабильном формате.
 * Он помогает frontend-слою строить меню и проверки доступа без знания модели.
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * Ресурс собирает базовые данные пользователя, роли и разрешения.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PluginManager $plugins */
        $plugins = app(PluginManager::class);
        $pluginMenu = $plugins->enabledAdminMenuForUser(
            fn (string $permission): bool => $this->hasPermission($permission),
        );

        $blogPluginEnabled = collect($plugins->all())
            ->contains(fn (array $plugin): bool => ($plugin['slug'] ?? null) === 'Blog' && ($plugin['enabled'] ?? false) === true);

        if ($blogPluginEnabled && $this->hasPermission('blog.access') && Schema::hasTable('record_types')) {
            $adminPath = app(AdminPathManager::class)->currentPath();

            $sections = DB::table('record_types')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name', 'slug']);

            foreach ($sections as $section) {
                $sectionName = (string) ($section->name ?? 'Раздел');
                $sectionSlug = (string) ($section->slug ?? '');

                if ($sectionSlug === '') {
                    continue;
                }

                $pluginMenu[] = [
                    'title' => 'Записи',
                    'path' => '/'.$adminPath.'/records/'.$sectionSlug.'/posts',
                    'group' => 'Записи',
                    'subgroup' => $sectionName,
                ];

                $pluginMenu[] = [
                    'title' => 'Категории',
                    'path' => '/'.$adminPath.'/records/'.$sectionSlug.'/categories',
                    'group' => 'Записи',
                    'subgroup' => $sectionName,
                ];

                $pluginMenu[] = [
                    'title' => 'Теги',
                    'path' => '/'.$adminPath.'/records/'.$sectionSlug.'/tags',
                    'group' => 'Записи',
                    'subgroup' => $sectionName,
                ];

                $pluginMenu[] = [
                    'title' => 'Настройки',
                    'path' => '/'.$adminPath.'/records/'.$sectionSlug.'/settings',
                    'group' => 'Записи',
                    'subgroup' => $sectionName,
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'roles' => $this->roleSlugs(),
            'permissions' => $this->permissionSlugs(),
            'plugin_menu' => $pluginMenu,
        ];
    }
}