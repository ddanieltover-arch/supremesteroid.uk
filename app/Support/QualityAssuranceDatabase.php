<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

class QualityAssuranceDatabase
{
    public static function isolatedSqlitePath(): string
    {
        return database_path('qa.sqlite');
    }

    public static function bindIsolatedSqliteIfNeeded(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $path = self::isolatedSqlitePath();
        if (! is_file($path)) {
            touch($path);
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'database.connections.sqlite.url' => null,
            'database.connections.pgsql.url' => null,
            'database.connections.pgsql_direct.url' => null,
        ]);

        DB::purge();
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
    }

    /**
     * @return array{default: string, driver: string, database: string}
     */
    public static function describe(): array
    {
        $default = (string) config('database.default');

        return [
            'default' => $default,
            'driver' => (string) config("database.connections.{$default}.driver"),
            'database' => (string) config("database.connections.{$default}.database"),
        ];
    }
}
