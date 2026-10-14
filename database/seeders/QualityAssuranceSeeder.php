<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\QualityAssurance\QualityAssuranceFixtureService;
use Illuminate\Database\Seeder;

class QualityAssuranceSeeder extends Seeder
{
    public function run(QualityAssuranceFixtureService $fixtures): void
    {
        $this->command?->info('Seeding isolated QA fixtures. This refuses production and Neon hosts.');
        $result = $fixtures->seed();
        $this->command?->info('QA customer A: '.$result['customer_a']['email']);
        $this->command?->info('QA customer B: '.$result['customer_b']['email']);
        $this->command?->info('QA admin: '.$result['admin']['email']);
        $this->command?->info('QA category: /category/'.$result['category']['slug']);
        $this->command?->info('QA brand: /brand/'.$result['brand']['slug']);
    }
}
