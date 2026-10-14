<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalogue\CataloguePublicationChecklistService;
use Illuminate\Console\Command;

class CataloguePublicationReportCommand extends Command
{
    protected $signature = 'catalogue:publication-report
        {--sku= : Limit to one SKU}
        {--limit=25 : Maximum products to report}
        {--status= : Optional product status filter}';

    protected $description = 'Print publication readiness reports for catalogue products.';

    public function handle(CataloguePublicationChecklistService $checklist): int
    {
        $query = Product::query()->orderBy('sku')->limit(max(1, (int) $this->option('limit')));

        if ($sku = $this->option('sku')) {
            $query->where('sku', $sku);
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->warn('No products matched.');

            return self::SUCCESS;
        }

        foreach ($products as $product) {
            foreach ($checklist->reportLines($product) as $line) {
                $this->line($line);
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
