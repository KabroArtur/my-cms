<?php

namespace App\Http\Middleware;

use App\Core\Security\Services\SecurityRuntimeSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ProtectAgainstDdos
{
    public function __construct(
        protected SecurityRuntimeSettings $security,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $settings = $this->security->all();

        if (! (bool) ($settings['security_rate_limit_enabled'] ?? true)) {
            return $next($request);
        }

        $ip = (string) ($request->ip() ?? '0.0.0.0');
        $banSeconds = max(60, (int) ($settings['security_ip_ban_seconds'] ?? 900));
        $banKey = $this->banKey($ip);

        if (Cache::has($banKey)) {
            $retryAfter = Cache::get($banKey);
            $seconds = is_numeric($retryAfter) ? max(1, ((int) $retryAfter) - now()->timestamp) : $banSeconds;

            return $this->tooManyRequests($request, $seconds);
        }

        $minuteLimit = max(30, (int) ($settings['security_rate_limit_per_minute'] ?? 180));
        $burstLimit = max(10, (int) ($settings['security_rate_limit_burst_per_10s'] ?? 45));

        $minuteCount = $this->hitCounter($this->minuteKey($ip), 61);
        $burstCount = $this->hitCounter($this->burstKey($ip), 11);

        if ($minuteCount > $minuteLimit || $burstCount > $burstLimit) {
            Cache::put($banKey, now()->addSeconds($banSeconds)->timestamp, now()->addSeconds($banSeconds));

            return $this->tooManyRequests($request, $banSeconds);
        }

        return $next($request);
    }

    protected function hitCounter(string $key, int $ttlSeconds): int
    {
        $count = Cache::add($key, 0, now()->addSeconds($ttlSeconds)) ? 0 : (int) Cache::get($key, 0);
        $count++;
        Cache::put($key, $count, now()->addSeconds($ttlSeconds));

        return $count;
    }

    protected function tooManyRequests(Request $request, int $seconds): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Слишком много запросов. Повторите позже.',
            ], 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        return response('Too Many Requests', 429, [
            'Retry-After' => (string) $seconds,
        ]);
    }

    protected function minuteKey(string $ip): string
    {
        return 'cms:ddos:minute:'.$ip.':'.intdiv(time(), 60);
    }

    protected function burstKey(string $ip): string
    {
        return 'cms:ddos:burst:'.$ip.':'.intdiv(time(), 10);
    }

    protected function banKey(string $ip): string
    {
        return 'cms:ddos:ban:'.$ip;
    }
}
