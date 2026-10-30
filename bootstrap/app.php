<?php

declare(strict_types=1);

use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        if ($trustedProxies === null || $trustedProxies === '' || $trustedProxies === '*') {
            $middleware->trustProxies(at: '*');
        } else {
            $middleware->trustProxies(at: array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', (string) $trustedProxies),
            ), static fn (string $value): bool => $value !== '')));
        }

        $middleware->append([
            SecurityHeaders::class,
            CanonicalHost::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $sanitize = static function (\Throwable $e): string {
            $message = preg_replace(
                '/(?:postgres|postgresql|mysql|pgsql):\/\/\S+/i',
                '[redacted-url]',
                $e->getMessage()
            ) ?? $e->getMessage();

            $caller = '';

            foreach ($e->getTrace() as $frame) {
                $class = $frame['class'] ?? '';

                if ($class !== '' && $class !== \Illuminate\Support\Manager::class) {
                    $caller = ' ('.$class.'::'.($frame['function'] ?? '').')';
                    break;
                }
            }

            return $e::class.': '.$message.$caller;
        };

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) use ($sanitize) {
            try {
                return app(\App\Exceptions\CustomerExceptionRenderer::class)->render($e, $request);
            } catch (\Throwable) {
                return response($sanitize($e), 500);
            }
        });

        $exceptions->respond(function ($response, \Throwable $e) use ($sanitize) {
            if ($response->getStatusCode() >= 500 && trim((string) $response->getContent()) === '') {
                return response($sanitize($e), $response->getStatusCode());
            }

            return $response;
        });
    })->create();
