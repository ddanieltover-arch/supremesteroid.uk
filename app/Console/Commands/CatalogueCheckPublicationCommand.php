<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalogue\CataloguePublicationChecklistService;
use Illuminate\Console\Command;

class CatalogueCheckPublicationCommand extends Command
{
    protected $signature = 'catalogue:check-publication
        {product : Product UUID or SKU}';

    protected $description = 'Run the authoritative publication checklist for one product.';

    public function handle(CataloguePublicationChecklistService $checklist): int
    {
        $product = $this->resolveProduct((string) $this->argument('product'));
        if ($product === null) {
            $this->error('Product not found.');

            return self::FAILURE;
        }

        foreach ($checklist->reportLines($product) as $line) {
            $this->line($line);
        }

        $result = $checklist->evaluate($product);

        return $result['can_publish'] ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveProduct(string $key): ?Product
    {
        return Product::query()
            ->where('id', $key)
            ->orWhere('sku', $key)
            ->orWhere('slug', $key)
            ->first();
    }
}
