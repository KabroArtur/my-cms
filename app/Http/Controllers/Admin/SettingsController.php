<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\Services\SettingsManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSiteSettingsRequest;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(protected SettingsManager $settings)
    {
    }

    public function show(): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission('settings.access') ?? false, 403);

        return response()->json([
            'data' => $this->settings->adminPayload(),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->validated());

        return response()->json([
            'data' => $this->settings->adminPayload(),
        ]);
    }
}