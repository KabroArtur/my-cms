<?php

namespace App\Http\Controllers\Admin;

use App\Core\Roles\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Контроллер отдает административный API для пользователей.
 * Он пока покрывает простой read-only список пользователей CMS.
 */
class UserController extends Controller
{
    /**
     * Контроллер возвращает пользователей для административного списка.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('id')
            ->paginate(20);

        return UserResource::collection($users);
    }

    /**
     * Контроллер создает нового пользователя и назначает ему роли.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->syncRoles($user, $data['role_slugs'] ?? []);

        $user->load(['roles.permissions', 'permissions']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет пользователя и его роли.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $attributes = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
        ];

        if (($data['password'] ?? null) !== null && $data['password'] !== '') {
            $attributes['password'] = $data['password'];
        }

        $user->fill($attributes);
        $user->save();

        $this->syncRoles($user, $data['role_slugs'] ?? []);

        return UserResource::make($user->load(['roles.permissions', 'permissions']));
    }

    /**
     * Контроллер синхронизирует роли пользователя по slug.
     *
     * @param array<int, string> $roleSlugs
     */
    protected function syncRoles(User $user, array $roleSlugs): void
    {
        $roleIds = Role::query()
            ->whereIn('slug', $roleSlugs)
            ->pluck('id')
            ->all();

        $user->roles()->sync($roleIds);
    }

    /**
     * Контроллер удаляет пользователя.
     */
    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        abort_if($user->username === 'admin', 422, 'Базового администратора удалить нельзя.');

        $user->delete();

        return response()->noContent();
    }
}