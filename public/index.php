<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

register_shutdown_function(function (): void {
    $error = error_get_last();

    if (! is_array($error) || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    // Never append plain text onto an already-started HTML response — that is
    // what made production show "The application could not start." as raw source.
    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    echo app_debug_enabled()
        ? bootstrap_error_page(
            'Fatal: '.redact_connection_string((string) $error['message']).' in '.$error['file'].':'.$error['line']
        )
        : bootstrap_error_page('The store is temporarily unavailable. Please try again shortly.');
});

$problems = production_env_problems();

if ($problems !== []) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo production_setup_page($problems);
    exit;
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

try {
    /** @var Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $app->handleRequest(Request::capture());
} catch (Throwable $e) {
    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    echo app_debug_enabled()
        ? bootstrap_error_page(
            $e::class.': '.redact_connection_string($e->getMessage()).' in '.$e->getFile().':'.$e->getLine()
        )
        : bootstrap_error_page('The store is temporarily unavailable. Please try again shortly.');
}

function production_env_problems(): array
{
    if (env_value('APP_ENV') !== 'production') {
        return [];
    }

    $problems = [];

    if (! app_key_is_valid(env_value('APP_KEY'))) {
        $problems[] = 'APP_KEY is missing or still a placeholder. Generate one with php artisan key:generate and reuse that same value on every deploy.';
    }

    if (! database_is_configured()) {
        $problems[] = 'Database is not configured. Set DATABASE_URL, or set DB_CONNECTION with DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.';
    }

    return $problems;
}

function database_is_configured(): bool
{
    $url = env_value('DATABASE_URL');
    if ($url !== '' && preg_match('/xxxx|YOUR_NEON|YOUR_PASSWORD|GENERATE/i', $url) !== 1) {
        return true;
    }

    $connection = env_value('DB_CONNECTION');
    if ($connection === 'sqlite' && env_value('DB_DATABASE') !== '') {
        return true;
    }

    return env_value('DB_HOST') !== '' && env_value('DB_DATABASE') !== '' && env_value('DB_USERNAME') !== '';
}

function app_debug_enabled(): bool
{
    return in_array(strtolower(env_value('APP_DEBUG')), ['1', 'true', 'yes', 'on'], true);
}

function app_key_is_valid(string $key): bool
{
    if ($key === '' || str_contains($key, 'GENERATE')) {
        return false;
    }

    $decoded = str_starts_with($key, 'base64:')
        ? base64_decode(substr($key, 7), true)
        : $key;

    return is_string($decoded) && in_array(strlen($decoded), [16, 32], true);
}

function env_value(string $name): string
{
    $value = getenv($name);

    if (is_string($value) && $value !== '') {
        return $value;
    }

    return (string) ($_ENV[$name] ?? $_SERVER[$name] ?? '');
}

function redact_connection_string(string $message): string
{
    return preg_replace('/(?:postgres|postgresql|mysql|pgsql):\/\/\S+/i', '[redacted-url]', $message) ?? $message;
}

function bootstrap_error_page(string $message): string
{
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>Temporary interruption | Supreme Steroids</title></head>'
        .'<body style="font-family:Figtree,system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.25rem;line-height:1.5;color:#0f172a;background:#f9f9f9">'
        .'<h1 style="font-size:1.5rem;margin:0 0 .75rem">Temporary service interruption</h1>'
        .'<p style="margin:0 0 1.25rem;color:#475569">'.$safe.'</p>'
        .'<p style="margin:0"><a href="/" style="color:#1e6fd9;font-weight:600;text-decoration:none">Reload homepage</a></p>'
        .'</body></html>';
}

function production_setup_page(array $problems): string
{
    $items = '';

    foreach ($problems as $problem) {
        $items .= '<li>'.htmlspecialchars($problem, ENT_QUOTES, 'UTF-8').'</li>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Store setup required</title></head>'
        .'<body style="font-family:sans-serif;max-width:40rem;margin:4rem auto;line-height:1.5">'
        .'<h1>Supreme Steroids is not configured</h1>'
        .'<p>The application is running, but production environment variables are missing or still placeholders. Set them on the host, then restart:</p>'
        .'<ul>'.$items.'</ul>'
        .'<p>Also set APP_ENV=production, APP_DEBUG=false, APP_URL=https://supremesteroid.uk, DB_CONNECTION=pgsql, DB_SSLMODE=require, and LOG_CHANNEL=stderr. Do not commit those values.</p>'
        .'</body></html>';
}
