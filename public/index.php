<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

register_shutdown_function(function (): void {
    $error = error_get_last();

    if (! is_array($error) || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo 'Fatal: '.redact_connection_string((string) $error['message']).' in '.$error['file'].':'.$error['line']."\n";
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
    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo $e::class.': '.redact_connection_string($e->getMessage()).' in '.$e->getFile().':'.$e->getLine();
}

function production_env_problems(): array
{
    $key = env_value('APP_KEY');
    $databaseUrl = env_value('DATABASE_URL');
    $problems = [];

    if (! app_key_is_valid($key)) {
        $problems[] = 'APP_KEY is missing or still a placeholder. Generate one with php artisan key:generate and set the same value on every deploy.';
    }

    if ($databaseUrl === '' || preg_match('/xxxx|YOUR_NEON|YOUR_PASSWORD/i', $databaseUrl) === 1) {
        $problems[] = 'DATABASE_URL is missing or still a placeholder. Set the Neon pooled connection string.';
    }

    if (env_value('DATABASE_URL_UNPOOLED') === '' || preg_match('/xxxx|YOUR_NEON|YOUR_PASSWORD/i', env_value('DATABASE_URL_UNPOOLED')) === 1) {
        $problems[] = 'DATABASE_URL_UNPOOLED is missing or still a placeholder. Migrations need the direct (non-pooler) Neon connection string.';
    }

    return $problems;
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

function production_setup_page(array $problems): string
{
    $items = '';

    foreach ($problems as $problem) {
        $items .= '<li>'.htmlspecialchars($problem, ENT_QUOTES, 'UTF-8').'</li>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Store setup required</title></head>'
        .'<body style="font-family:sans-serif;max-width:40rem;margin:4rem auto;line-height:1.5">'
        .'<h1>Supreme Steroids is not configured</h1>'
        .'<p>The container is running, but production environment variables are still placeholders. Set these on the Vercel project, then redeploy:</p>'
        .'<ul>'.$items.'</ul>'
        .'<p>Also set APP_ENV=production, APP_DEBUG=false, APP_URL=https://supremesteroid.uk, DB_CONNECTION=pgsql, DB_SSLMODE=require, and LOG_CHANNEL=stderr. Do not commit those values.</p>'
        .'</body></html>';
}
