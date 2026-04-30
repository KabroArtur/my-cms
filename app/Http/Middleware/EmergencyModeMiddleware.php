<?php

namespace App\Http\Middleware;

use App\Core\Security\Services\AdminPathManager;
use App\Core\Security\Services\SecurityRuntimeSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmergencyModeMiddleware
{
    public function __construct(
        protected SecurityRuntimeSettings $security,
        protected AdminPathManager $adminPath,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $settings = $this->security->all();

        if (! (bool) ($settings['security_emergency_mode'] ?? false)) {
            return $next($request);
        }

        if ($this->adminPath->isAdminRequest($request) || $request->is('up')) {
            return $next($request);
        }

        $message = (string) ($settings['security_emergency_message'] ?? 'Сайт временно недоступен.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 503, [
                'Retry-After' => '120',
            ]);
        }

        return response($message, 503, [
            'Retry-After' => '120',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
