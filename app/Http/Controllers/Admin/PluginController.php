<?php

namespace App\Http\Controllers\Admin;

use App\Core\Modules\Services\PluginManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PluginController extends Controller
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('plugins.access'), 403);

        return response()->json([
            'items' => $this->plugins->all(),
        ]);
    }

    public function install(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('plugins.install'), 403);

        try {
            $plugin = $this->plugins->install($slug);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['plugin' => $plugin]);
    }

    public function enable(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('plugins.enable'), 403);

        try {
            $plugin = $this->plugins->enable($slug);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['plugin' => $plugin]);
    }

    public function disable(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('plugins.enable'), 403);

        try {
            $plugin = $this->plugins->disable($slug);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['plugin' => $plugin]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('plugins.delete'), 403);

        $payload = $request->validate([
            'drop_data' => ['nullable', 'boolean'],
            'remove_files' => ['nullable', 'boolean'],
        ]);

        try {
            $this->plugins->delete(
                $slug,
                (bool) ($payload['drop_data'] ?? false),
                (bool) ($payload['remove_files'] ?? false),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
