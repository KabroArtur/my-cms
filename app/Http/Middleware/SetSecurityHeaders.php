<?php

namespace App\Http\Middleware;

use App\Core\Security\Services\AdminPathManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware выставляет базовые security headers для всех ответов.
 * Он закрывает типовые риски clickjacking, MIME sniffing и лишней утечки referrer.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $cspNonce = $this->generateNonce();

        $request->attributes->set('csp_nonce', $cspNonce);
        View::share('cspNonce', $cspNonce);

        /** @var Response $response */
        $response = $next($request);
        $contentSecurityPolicy = $this->buildContentSecurityPolicy($cspNonce);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $contentSecurityPolicy);

        $adminPath = app(AdminPathManager::class)->currentPath();
        if ($request->is($adminPath) || $request->is($adminPath.'/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    protected function buildContentSecurityPolicy(string $cspNonce): string
    {
        $viteOrigin = $this->viteDevServerOrigin();
        $googleFontsStyleOrigin = 'https://fonts.googleapis.com';
        $googleFontsAssetOrigin = 'https://fonts.gstatic.com';

        $imgSources = ["'self'", 'data:', 'blob:'];
        $fontSources = ["'self'", 'data:', $googleFontsAssetOrigin];
        $mediaSources = ["'self'", 'data:', 'blob:'];
        $scriptSources = ["'self'", 'blob:', sprintf("'nonce-%s'", $cspNonce)];
        $styleSources = ["'self'", "'unsafe-inline'", $googleFontsStyleOrigin];
        $connectSources = ["'self'"];

        if ($viteOrigin !== null) {
            $imgSources[] = $viteOrigin;
            $fontSources[] = $viteOrigin;
            $mediaSources[] = $viteOrigin;
            $scriptSources[] = $viteOrigin;
            $styleSources[] = $viteOrigin;
            $connectSources[] = $viteOrigin;

            $viteWsOrigin = preg_replace('/^http/i', 'ws', $viteOrigin);

            if (is_string($viteWsOrigin) && $viteWsOrigin !== '') {
                $connectSources[] = $viteWsOrigin;
            }
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "form-action 'self'",
            'img-src '.implode(' ', array_unique($imgSources)),
            'font-src '.implode(' ', array_unique($fontSources)),
            'media-src '.implode(' ', array_unique($mediaSources)),
            'script-src '.implode(' ', array_unique($scriptSources)),
            'style-src '.implode(' ', array_unique($styleSources)),
            'connect-src '.implode(' ', array_unique($connectSources)),
        ]);
    }

    protected function generateNonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    protected function viteDevServerOrigin(): ?string
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $value = trim((string) file_get_contents($hotFile));

        if ($value === '') {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);
        $port = parse_url($value, PHP_URL_PORT);

        if (! is_string($scheme) || ! is_string($host)) {
            return null;
        }

        return $port === null
            ? sprintf('%s://%s', $scheme, $host)
            : sprintf('%s://%s:%d', $scheme, $host, $port);
    }
}