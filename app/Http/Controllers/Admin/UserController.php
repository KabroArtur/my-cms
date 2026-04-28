<?php

namespace App\Http\Controllers\Admin;

use App\Core\Roles\Models\Role;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    public function store(StoreUserRequest $request, SecurityAuditLogger $audit): JsonResponse
    {
        $data = $request->validated();

        $this->ensureActorCanManageRoles($request->user(), $data['role_slugs'] ?? []);

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->syncRoles($user, $data['role_slugs'] ?? []);

        $user->load(['roles.permissions', 'permissions']);

        $audit->log('users.created', $request->user(), [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'role_slugs' => $user->roleSlugs(),
        ]);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет пользователя и его роли.
     */
    public function update(UpdateUserRequest $request, User $user, SecurityAuditLogger $audit): UserResource
    {
        $data = $request->validated();

        $this->ensureActorCanManageRoles(
            $request->user(),
            $data['role_slugs'] ?? [],
            $user,
        );

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

        $audit->log('users.updated', $request->user(), [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
            'role_slugs' => $user->fresh()->roleSlugs(),
        ]);

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
     * Контроллер не дает управлять ролями без отдельного права на роли.
     * Это закрывает эскалацию через API пользователей.
     *
     * @param array<int, string> $requestedRoleSlugs
     */
    protected function ensureActorCanManageRoles(?User $actor, array $requestedRoleSlugs, ?User $target = null): void
    {
        $normalizedRequested = collect($requestedRoleSlugs)
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $normalizedCurrent = $target === null
            ? []
            : $target->roles()
                ->pluck('slug')
                ->sort()
                ->values()
                ->all();

        if ($normalizedRequested === $normalizedCurrent) {
            return;
        }

        if ($actor?->hasPermission('users.manage_roles')) {
            return;
        }

        throw new HttpException(403, 'Недостаточно прав для изменения ролей пользователя.');
    }

    /**
     * Контроллер удаляет пользователя.
     */
    public function destroy(User $user, SecurityAuditLogger $audit): Response
    {
        $this->authorize('delete', $user);

        abort_if($user->username === 'admin', 422, 'Базового администратора удалить нельзя.');

        $audit->log('users.deleted', request()->user(), [
            'target_user_id' => $user->id,
            'target_username' => $user->username,
        ]);

        $user->delete();

        return response()->noContent();
    }
}