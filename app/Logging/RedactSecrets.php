<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

class RedactSecrets
{
    public function __invoke(mixed $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record): LogRecord {
                return $record->with(message: $this->redact($record->message));
            });
        }
    }

    protected function redact(string $message): string
    {
        $message = preg_replace(
            '/(?:postgres|postgresql|mysql|redis|rediss):\/\/\S+/i',
            '[redacted-url]',
            $message
        ) ?? $message;

        return preg_replace(
            '/\b(password|secret|token|authorization|app_key|cron_secret)\s*[:=]\s*\S+/i',
            '$1=[redacted]',
            $message
        ) ?? $message;
    }
}
