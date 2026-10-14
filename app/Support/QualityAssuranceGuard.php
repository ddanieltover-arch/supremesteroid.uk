<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class QualityAssuranceGuard
{
    public static function assertSafeEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('QA fixtures cannot run when APP_ENV=production.');
        }

        if (! app()->environment(['local', 'testing', 'qa'])) {
            throw new RuntimeException('QA fixtures may only run in the local, testing, or qa environment.');
        }

        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");
        $host = strtolower((string) config("database.connections.{$connection}.host"));
        $url = strtolower((string) (config("database.connections.{$connection}.url") ?? ''));

        if ($driver !== 'sqlite') {
            $url = $url !== '' ? $url : strtolower((string) env('DATABASE_URL', ''));
        }

        if (str_contains($host, 'neon.tech') || str_contains($url, 'neon.tech')) {
            throw new RuntimeException('QA fixtures refuse Neon or other production PostgreSQL hosts.');
        }

        if ($driver === 'sqlite') {
            if ($database === ':memory:' || $database === '') {
                return;
            }

            $path = str_replace('\\', '/', $database);
            if (! str_contains($path, '/database/') && ! str_ends_with($path, '.sqlite')) {
                throw new RuntimeException('QA fixtures only accept the application sqlite files.');
            }

            return;
        }

        if (! filter_var(env('QA_ALLOW_NON_SQLITE', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('QA fixtures require sqlite unless QA_ALLOW_NON_SQLITE is enabled for a local database.');
        }

        if (! in_array($host, ['127.0.0.1', 'localhost', ''], true)) {
            throw new RuntimeException('Non-sqlite QA fixtures are limited to localhost.');
        }
    }
}
