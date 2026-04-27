<?php

namespace App\Http\Controllers\Admin;

use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
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
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        $this->syncPermissions($role, $data['permission_slugs'] ?? []);

        return RoleResource::make($role->load('permissions')->loadCount('users'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет существующую роль.
     */
    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        $role->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);
        $role->save();

        $this->syncPermissions($role, $data['permission_slugs'] ?? []);

        return RoleResource::make($role->load('permissions')->loadCount('users'));
    }

    /**
     * @param array<int, string> $permissionSlugs
     */
    protected function syncPermissions(Role $role, array $permissionSlugs): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }
}