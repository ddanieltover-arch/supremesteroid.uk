<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReadinessService
{
    /**
     * @return array{status: string, checks: array<string, string>}
     */
    public function report(): array
    {
        $checks = [
            'application' => 'pass',
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'storage' => $this->storage(),
            'mail' => $this->mail(),
            'cron_secret' => $this->present('store.cron_secret') ? 'pass' : 'fail',
            'payment_instructions' => $this->payments(),
            'frontend_assets' => is_file(public_path('build/manifest.json')) ? 'pass' : 'fail',
            'app_url' => $this->appUrl(),
            'app_debug' => config('app.debug') ? 'fail' : 'pass',
            'app_key' => $this->present('app.key') ? 'pass' : 'fail',
            'sessions' => config('session.driver') === 'file' && app()->environment('production') ? 'fail' : $this->sessionStore(),
            'cache' => config('cache.default') === 'file' && app()->environment('production') ? 'fail' : 'pass',
        ];

        return [
            'status' => in_array('fail', $checks, true) ? 'NOT_READY' : 'READY',
            'checks' => $checks,
        ];
    }

    protected function database(): string
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return 'pass';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    protected function migrations(): string
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return 'fail';
            }

            $files = glob(database_path('migrations/*.php')) ?: [];
            $ran = DB::table('migrations')->count();

            return $ran >= count($files) ? 'pass' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    protected function storage(): string
    {
        $disk = (string) config('filesystems.cloud', '');

        if (app()->environment('production') && in_array($disk, ['', 'local', 'public'], true)) {
            return 'fail';
        }

        if ($disk === '') {
            return 'fail';
        }

        try {
            Storage::disk($disk);

            return 'pass';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    protected function mail(): string
    {
        $mailer = (string) config('mail.default', '');
        if ($mailer === '') {
            return 'fail';
        }

        if (app()->environment('production') && in_array($mailer, ['array', 'log'], true)) {
            return 'fail';
        }

        if ($mailer === 'smtp' && ! $this->present('mail.mailers.smtp.host')) {
            return 'fail';
        }

        return 'pass';
    }

    protected function payments(): string
    {
        $bank = array_filter(config('payments.bank_transfer', []));
        $crypto = config('payments.crypto.networks', []);

        return ($bank !== [] || $crypto !== []) ? 'pass' : 'fail';
    }

    protected function appUrl(): string
    {
        $url = (string) config('app.url', '');

        if (! str_starts_with($url, 'https://')) {
            return app()->environment('production') ? 'fail' : 'pass';
        }

        return 'pass';
    }

    protected function sessionStore(): string
    {
        if (config('session.driver') !== 'database') {
            return 'pass';
        }

        return Schema::hasTable('sessions') ? 'pass' : 'fail';
    }

    protected function present(string $key): bool
    {
        $value = config($key);

        return is_string($value) && $value !== '';
    }
}
