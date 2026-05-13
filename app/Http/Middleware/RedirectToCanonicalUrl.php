<?php

namespace App\Http\Middleware;

use App\Core\Settings\Services\SettingsManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware приводит запросы к каноническому host/scheme приложения.
 * Это устраняет расхождения между http/https и разными локальными host при генерации URL и cookie.
 */
class RedirectToCanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.enforce_canonical_url', false)) {
            return $next($request);
        }

        $canonicalUrl = app(SettingsManager::class)->canonicalBaseUrl($request->getHost());

        if ($canonicalUrl === '') {
            return $next($request);
        }

        $canonicalScheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);
        $canonicalPort = parse_url($canonicalUrl, PHP_URL_PORT);

        if (! is_string($canonicalScheme) || ! is_string($canonicalHost)) {
            return $next($request);
        }

        $currentHost = $request->getHost();
        $currentPort = $request->getPort();
        $currentScheme = $request->getScheme();

        $schemeMatches = $currentScheme === $canonicalScheme;
        $hostMatches = $currentHost === $canonicalHost;
        $portMatches = (int) ($canonicalPort ?? $this->defaultPortForScheme($canonicalScheme)) === (int) $currentPort;

        if ($schemeMatches && $hostMatches && $portMatches) {
            return $next($request);
        }

        $target = rtrim($canonicalUrl, '/').'/'.ltrim($request->getRequestUri(), '/');

        return redirect()->to($target, 308);
    }

    protected function defaultPortForScheme(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}