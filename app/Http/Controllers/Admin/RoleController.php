<?php

namespace App\Http\Controllers\Admin;

use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use App\Http\Resources\Admin\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Контроллер отдает административный API для ролей.
 * Он пока покрывает простой read-only список ролей и их разрешений.
 */
class RoleController extends Controller
{
    /**
     * Контроллер возвращает список ролей.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get();

        return RoleResource::collection($roles);
    }

    /**
     * Контроллер создает новую роль.
     */
    public function store(StoreRoleRequest $request, SecurityAuditLogger $audit): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        $this->syncPermissions($role, $data['permission_slugs'] ?? []);

        $audit->log('roles.created', $request->user(), [
            'target_role_id' => $role->id,
            'target_role_slug' => $role->slug,
            'permission_slugs' => $role->permissions()->pluck('slug')->all(),
        ]);

        return RoleResource::make($role->load('permissions')->loadCount('users'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет существующую роль.
     */
    public function update(UpdateRoleRequest $request, Role $role, SecurityAuditLogger $audit): RoleResource
    {
        $data = $request->validated();

        $role->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
        $role->save();

        $this->syncPermissions($role, $data['permission_slugs'] ?? []);

        $audit->log('roles.updated', $request->user(), [
            'target_role_id' => $role->id,
            'target_role_slug' => $role->slug,
            'permission_slugs' => $role->permissions()->pluck('slug')->all(),
        ]);

        return RoleResource::make($role->load('permissions')->loadCount('users'));
    }

    /**
     * @param array<int, string> $permissionSlugs
     */
    protected function syncPermissions(Role $role, array $permissionSlugs): void
    {
        $permissionSlugs = $this->normalizePermissionSlugs($permissionSlugs);

        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    /**
     * @param array<int, string> $permissionSlugs
     * @return array<int, string>
     */
    protected function normalizePermissionSlugs(array $permissionSlugs): array
    {
        $selected = collect($permissionSlugs)
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->values();

        $parentChildren = [
            'pages.access' => [
                'pages.create',
                'pages.update',
                'pages.delete',
                'pages.additional_fields.manage',
            ],
            'users.access' => [
                'users.create',
                'users.update',
                'users.delete',
                'users.manage_roles',
            ],
            'roles.access' => [
                'roles.create',
                'roles.update',
            ],
            'media.access' => [
                'media.upload',
                'media.delete',
                'media.manage_folders',
            ],
            'settings.access' => [
                'settings.general.manage',
                'settings.appearance.manage',
                'settings.cache.manage',
                'settings.security.manage',
            ],
        ];

        $selectedSet = $selected->flip();

        foreach ($parentChildren as $parent => $children) {
            if (! $selectedSet->has($parent)) {
                foreach ($children as $child) {
                    $selectedSet->forget($child);
                }
            }
        }

        foreach ($parentChildren as $parent => $children) {
            foreach ($children as $child) {
                if ($selectedSet->has($child)) {
                    $selectedSet->put($parent, true);
                }
            }
        }

        return $selectedSet->keys()->values()->all();
    }
}