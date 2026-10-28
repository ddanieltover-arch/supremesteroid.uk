<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalogue\Import\CatalogueBrokenImageRepairService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CatalogueRepairBrokenImagesCommand extends Command
{
    protected $signature = 'catalogue:repair-broken-images
        {--base-url=https://steroids-uk.com : Source storefront base URL}
        {--limit= : Max broken products to process}
        {--execute : Download images and update product_images (default dry-run)}
        {--delay-ms=750 : Delay between HTTP requests}';

    protected $description = 'Repair broken product image hotlinks by matching and downloading images from a reference storefront (steroids-uk.com).';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $execute = (bool) $this->option('execute');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : null;
        $delayMs = max(0, (int) $this->option('delay-ms'));

        $service = new CatalogueBrokenImageRepairService(
            parser: app(\App\Services\Catalogue\Import\PublicProductPageParser::class),
            delayMs: $delayMs,
        );

        $this->info(($execute ? 'Executing' : 'Dry-run').' image repair from '.$baseUrl);
        if ($limit !== null) {
            $this->line('Limit: '.$limit);
        }

        $report = $service->repair($baseUrl, $execute, $limit);

        $dir = storage_path('app/catalogue-import/audits');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/image-repair-steroids-uk.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Broken candidates', (string) $report['broken_count']],
                ['Source products indexed', (string) $report['source_products']],
                ['Matched / repaired', (string) count($report['repaired'])],
                ['Unmatched', (string) count($report['unmatched'])],
                ['Skipped / failed', (string) count($report['skipped'])],
            ]
        );

        foreach (array_slice($report['repaired'], 0, 15) as $row) {
            $this->line(sprintf(
                '  ✓ %s ← %s (%s %.2f)',
                $row['name'] ?? '',
                $row['source_url'] ?? '',
                $row['match'] ?? '',
                (float) ($row['score'] ?? 0)
            ));
        }

        if (count($report['unmatched']) > 0) {
            $this->newLine();
            $this->warn('Unmatched examples:');
            foreach (array_slice($report['unmatched'], 0, 10) as $row) {
                $this->line('  - '.($row['name'] ?? ''));
            }
        }

        $this->newLine();
        $this->info('Audit written: '.$path);

        if (! $execute) {
            $this->warn('Dry-run only. Pass --execute to download images and update product_images.');
        }

        return self::SUCCESS;
    }
}
