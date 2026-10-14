<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QualityAssurance\QualityAssuranceFixtureService;
use App\Support\QualityAssuranceDatabase;
use Illuminate\Console\Command;

class SeedQualityAssuranceCommand extends Command
{
    protected $signature = 'qa:seed';

    protected $description = 'Seed isolated local/testing QA fixtures. Refuses production and Neon.';

    public function handle(QualityAssuranceFixtureService $fixtures): int
    {
        $result = $fixtures->seed();
        $connection = QualityAssuranceDatabase::describe();
        $this->info('QA fixtures are ready on the isolated local/testing database.');
        $this->line('Connection: '.$connection['default'].' / '.$connection['driver']);
        $this->line('Database: '.$connection['database']);
        $this->line('Customer A: '.$result['customer_a']['email']);
        $this->line('Customer B: '.$result['customer_b']['email']);
        $this->line('Admin: '.$result['admin']['email']);
        $this->line('Category: /category/'.$result['category']['slug']);
        $this->line('Brand: /brand/'.$result['brand']['slug']);

        return self::SUCCESS;
    }
}
