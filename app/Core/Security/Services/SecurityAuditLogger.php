<?php

namespace App\Core\Security\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Сервис пишет компактный аудит критичных административных действий.
 * Он складывает структурированный контекст в отдельный security log channel.
 */
class SecurityAuditLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(string $action, ?Authenticatable $actor = null, array $context = []): void
    {
        $request = request();

        Log::channel('security')->info('cms.audit', [
            'action' => $action,
            'actor_id' => $actor?->getAuthIdentifier(),
            'ip' => $request?->ip(),
            'url' => $request?->fullUrl(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
        ]);
    }
}