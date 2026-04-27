<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuthenticatedUserResource;
use Illuminate\Http\Request;

/**
 * Контроллер отдает данные текущей административной сессии.
 * Он нужен для frontend-слоя админки и проверки доступов в интерфейсе.
 */
class AdminSessionController extends Controller
{
    /**
     * Контроллер возвращает текущего пользователя и его права.
     */
    public function show(Request $request): AuthenticatedUserResource
    {
        $user = $request->user()->loadMissing(['roles.permissions', 'permissions']);

        return AuthenticatedUserResource::make($user);
    }
}