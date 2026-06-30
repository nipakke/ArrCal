<?php

declare(strict_types=1);

namespace ArrCal\Kernel;

use ArrCal\Handler\CalendarApiHandler;
use ArrCal\Service\ApiCache;
use ArrCal\Service\CalendarAggregator;
use ArrCal\Service\InstanceConfig;
use ArrCal\Service\IpResolver;
use ArrCal\Service\RadarrService;
use ArrCal\Service\RateLimiter;
use ArrCal\Service\SonarrService;
use FastRoute;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;

/**
 * Application kernel — DI container, route registration, and HTTP server bootstrap.
 *
 * Wires the *arr services (Radarr + Sonarr) with in-memory caching and a
 * unified calendar aggregator. Serves the SPA at / and JSON API at /api/calendar.
 *
 * Infrastructure layers (rate limiting, IP resolution, static file serving)
 * are applied as middleware to every request.
 */
final class ServerKernel
{
    private readonly IpResolver $ipResolver;

    private readonly RateLimiter $rateLimiter;

    private readonly string $publicDir;

    private readonly bool $isDev;

    /** @var array<string, string> */
    private static array $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'html' => 'text/html',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
    ];

    public function __construct()
    {
        $this->loadEnvironment();
        $this->publicDir = __DIR__.'/../public';
        $this->ipResolver = new IpResolver;
        $this->rateLimiter = new RateLimiter;
        $this->isDev = ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '') === 'local';
    }

    /**
     * Load environment variables from the .env file (if it exists).
     *
     * Values already present in the environment (getenv or $_ENV) are
     * preserved and not overwritten. Both $_ENV and putenv() are set
     * to ensure compatibility with both getenv() and $_ENV access patterns.
     */
    private function loadEnvironment(): void
    {
        $envFile = __DIR__.'/../.env';

        if (! file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Only set if not already defined by the actual environment
            if (! isset($_ENV[$key]) && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    private function resolveMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::$mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Serve a static file from the public/ directory with path-traversal protection.
     */
    private function serveStatic(string $requestPath): ?Response
    {
        $filePath = $this->publicDir.$requestPath;
        $realFile = realpath($filePath);

        if ($realFile === false) {
            return null;
        }

        $realPublic = realpath($this->publicDir);

        if ($realPublic === false || ! str_starts_with($realFile, $realPublic)) {
            return null;
        }

        if (! is_file($realFile) || ! is_readable($realFile)) {
            return null;
        }

        $content = file_get_contents($realFile);

        if ($content === false) {
            return null;
        }

        $mime = $this->resolveMime($realFile);

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) \strlen($content),
        ];

        if (! $this->isDev) {
            $headers['Cache-Control'] = 'public, max-age=604800, immutable';
        }

        return new Response(200, $headers, $content);
    }

    public function run(): void
    {
        $loop = Loop::get();

        // ── Services ────────────────────────────────────────────────

        $browser = new Browser($loop);

        $instanceConfig = new InstanceConfig;

        $radarrServices = [];
        foreach ($instanceConfig->getRadarrInstances() as $cfg) {
            $radarrServices[] = new RadarrService(
                $browser,
                $cfg['id'],
                $cfg['url'],
                $cfg['apiKey'] ?? '',
                $cfg['label'] ?? null,
                publicUrl: $cfg['publicUrl'],
            );
        }

        $sonarrServices = [];
        foreach ($instanceConfig->getSonarrInstances() as $cfg) {
            $sonarrServices[] = new SonarrService(
                $browser,
                $cfg['id'],
                $cfg['url'],
                $cfg['apiKey'] ?? '',
                $cfg['label'] ?? null,
                publicUrl: $cfg['publicUrl'],
            );
        }

        $cache = new ApiCache;
        $aggregator = new CalendarAggregator($radarrServices, $sonarrServices, $cache);

        $calendarApiHandler = new CalendarApiHandler($aggregator);

        // ── App bootstrap config (injected into SPA HTML) ──────────

        $appConfig = \json_encode([
            'radarr' => \array_map(
                static fn (RadarrService $r): array => [
                    'id' => $r->getId(),
                    'label' => $r->getLabel(),
                    'enabled' => $r->isEnabled(),
                    'url' => $r->getPublicUrl(),
                    'error' => $r->getError(),
                ],
                $radarrServices,
            ),
            'sonarr' => \array_map(
                static fn (SonarrService $s): array => [
                    'id' => $s->getId(),
                    'label' => $s->getLabel(),
                    'enabled' => $s->isEnabled(),
                    'url' => $s->getPublicUrl(),
                    'error' => $s->getError(),
                ],
                $sonarrServices,
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $configScript = '<script>window.__ARR_CONFIG__='.$appConfig.';</script>';

        // ── Routes ──────────────────────────────────────────────────

        $dispatcher = FastRoute\simpleDispatcher(
            function (RouteCollector $r) use ($calendarApiHandler, $appConfig): void {
                $r->addRoute('GET', '/api/calendar', $calendarApiHandler);
                $r->addRoute('GET', '/api/config', static function () use ($appConfig): Response {
                    return new Response(
                        200,
                        ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'],
                        $appConfig,
                    );
                });
            },
        );

        // ── HTTP server with middleware ─────────────────────────────

        $http = new HttpServer(function (ServerRequestInterface $request) use ($dispatcher, $configScript) {
            // ── Rate limiting ───────────────────────────────────────
            $clientIp = $this->ipResolver->resolve($request);

            if (! $this->rateLimiter->isAllowed($clientIp)) {
                $retryAfter = $this->rateLimiter->getRetryAfterSeconds($clientIp);

                return new Response(429, [
                    'Content-Type' => 'text/plain',
                    'Retry-After' => (string) $retryAfter,
                ], 'Too Many Requests');
            }

            // ── Routing ─────────────────────────────────────────────
            $routeInfo = $dispatcher->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath(),
            );

            if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
                return $routeInfo[1]($request);
            }

            // ── SPA root ────────────────────────────────────────────
            if ($request->getMethod() === 'GET' && $request->getUri()->getPath() === '/') {
                $spaFile = $this->publicDir.'/index.html';

                if (file_exists($spaFile)) {
                    $content = \file_get_contents($spaFile);

                    if ($content !== false) {
                        $content = \str_replace('<!--ARR_CONFIG-->', $configScript, $content);

                        return new Response(200, ['Content-Type' => 'text/html'], $content);
                    }
                }

                return new Response(200, ['Content-Type' => 'text/plain'], 'ArrCal — build the SPA with: pnpm build:frontend');
            }

            // ── Static file fallback ────────────────────────────────
            if ($request->getMethod() === 'GET') {
                $staticResponse = $this->serveStatic($request->getUri()->getPath());

                if ($staticResponse !== null) {
                    return $staticResponse;
                }
            }

            return new Response(404, ['Content-Type' => 'text/plain'], '404 - Not Found');
        });

        // ── Listen ──────────────────────────────────────────────────
        $port = (int) ($_ENV['PORT'] ?? getenv('PORT') ?: 8080);
        $socket = new SocketServer("0.0.0.0:{$port}", []);
        $http->listen($socket);

        echo 'ArrCal running on http://0.0.0.0:'.$port.PHP_EOL;

        $loop->run();
    }
}
