<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalogue\Import\CatalogueCdnImageLocalizeService;
use App\Services\Catalogue\CataloguePresentation;
use App\Services\Storefront\HomepageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CatalogueLocalizeCdnImagesCommand extends Command
{
    protected $signature = 'catalogue:localize-cdn-images
        {--limit= : Max CDN image rows to process}
        {--execute : Download images and update product_images (default dry-run)}
        {--delay-ms=400 : Delay between HTTP requests}';

    protected $description = 'Download CDN/reference product images into local public storage so they become usable catalogue images.';

    public function handle(CataloguePresentation $presentation): int
    {
        $execute = (bool) $this->option('execute');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : null;
        $delayMs = max(0, (int) $this->option('delay-ms'));

        $service = new CatalogueCdnImageLocalizeService(
            presentation: $presentation,
            delayMs: $delayMs,
        );

        $this->info(($execute ? 'Executing' : 'Dry-run').' CDN image localization to public disk');
        if ($limit !== null) {
            $this->line('Limit: '.$limit);
        }

        $report = $service->localize($execute, $limit);

        $dir = storage_path('app/catalogue-import/audits');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/cdn-localize.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Candidates', (string) $report['candidates']],
                ['Localized / matched', (string) count($report['localized'])],
                ['Failed', (string) count($report['failed'])],
                ['Skipped', (string) count($report['skipped'])],
            ]
        );

        foreach (array_slice($report['localized'], 0, 15) as $row) {
            $this->line(sprintf(
                '  ✓ %s → %s',
                $row['name'] ?? '',
                $row['stored_url'] ?? ($row['fetch_url'] ?? $row['remote_url'] ?? '')
            ));
        }

        if (count($report['failed']) > 0) {
            $this->newLine();
            $this->warn('Failed examples:');
            foreach (array_slice($report['failed'], 0, 10) as $row) {
                $this->line('  - '.($row['name'] ?? '').': '.($row['reason'] ?? ''));
            }
        }

        if ($execute) {
            app(HomepageService::class)->flush();
        }

        $this->newLine();
        $this->info('Audit written: '.$path);

        if (! $execute) {
            $this->warn('Dry-run only. Pass --execute to download images and update product_images.');
        }

        return self::SUCCESS;
    }
}