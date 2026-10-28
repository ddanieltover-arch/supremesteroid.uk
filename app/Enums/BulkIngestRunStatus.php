<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkIngestRunStatus: string
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case PAUSED = 'PAUSED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::PAUSED => 'Paused / resumable',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }
}
