<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QualityAssurance\QualityAssuranceFixtureService;
use App\Support\QualityAssuranceDatabase;
use Illuminate\Console\Command;

class ResetQualityAssuranceCommand extends Command
{
    protected $signature = 'qa:reset
        {--env= : Optional reminder; command still refuses production/Neon}';

    protected $description = 'Delete only QA/test fixtures from the local/testing database. Refuses production and Neon. See docs/QA_CLEANUP.md.';

    public function handle(QualityAssuranceFixtureService $fixtures): int
    {
        $deleted = $fixtures->reset();
        $connection = QualityAssuranceDatabase::describe();
        $this->info("Removed {$deleted} QA fixture records.");
        $this->line('Connection: '.$connection['default'].' / '.$connection['driver']);
        $this->line('Database: '.$connection['database']);

        return self::SUCCESS;
    }
}
