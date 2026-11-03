<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\CatalogueStoredImageCdnFallback;
use App\Services\Catalogue\Import\CatalogueCdnImageObjectStorageMigrateService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CatalogueMigrateCdnImagesToObjectStorageCommand extends Command
{
    protected $signature = 'catalogue:migrate-cdn-images-to-object-storage
        {--limit= : Max image rows to process}
        {--execute : Download images into object storage and update product_images (default dry-run)}
        {--delay-ms=400 : Delay between HTTP requests}
        {--seo-only : Only rewrite BunnyCDN seo_metadata.og_image_url values}';

    protected $description = 'Copy BunnyCDN /storage product images into first-party object storage for SEO-stable URLs.';

    public function handle(
        CataloguePresentation $presentation,
        ObjectStorageService $storage,
        CatalogueStoredImageCdnFallback $cdnFallback,
    ): int {
        $execute = (bool) $this->option('execute');
        $seoOnly = (bool) $this->option('seo-only');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : null;
        $delayMs = max(0, (int) $this->option('delay-ms'));

        $disk = $storage->diskName();
        $cloudUrl = (string) (config('filesystems.disks.'.$disk.'.url') ?: '');

        $this->info(($execute ? 'Executing' : 'Dry-run').' CDN → object-storage image migration');
        $this->line('Cloud disk: '.$disk.($cloudUrl !== '' ? ' ('.$cloudUrl.')' : ' (no public URL configured)'));

        if ($execute && app()->environment('production') && in_array($disk, ['local', 'public'], true)) {
            $this->error('Production OBJECT_STORAGE_DISK must be s3 or r2 before migrating images.');

            return self::FAILURE;
        }

        if ($execute && $cloudUrl === '' && in_array($disk, ['s3', 'r2'], true)) {
            $this->warn('Cloud disk has no public URL (AWS_URL / R2_URL). Migrated keys may not resolve to first-party absolute URLs until configured.');
        }

        $service = new CatalogueCdnImageObjectStorageMigrateService(
            presentation: $presentation,
            storage: $storage,
            cdnFallback: $cdnFallback,
            delayMs: $delayMs,
        );

        if ($seoOnly) {
            if (! $execute) {
                $this->warn('SEO-only mode requires --execute to rewrite og_image_url rows.');

                return self::FAILURE;
            }

            $rewritten = $service->rewriteBunnyOgImageUrls();
            $this->table(['Metric', 'Count'], [['SEO og_image_url rewritten', (string) count($rewritten)]]);
            foreach (array_slice($rewritten, 0, 15) as $row) {
                $this->line(sprintf(
                    '  ✓ %s: %s → %s',
                    $row['slug'] ?? $row['product_id'] ?? '',
                    $row['before'] ?? '',
                    $row['after'] ?? '(cleared)'
                ));
            }

            return self::SUCCESS;
        }

        if ($limit !== null) {
            $this->line('Limit: '.$limit);
        }

        $report = $service->migrate($execute, $limit);

        $dir = storage_path('app/catalogue-import/audits');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/cdn-object-storage-migrate.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Candidates', (string) $report['candidates']],
                ['Migrated / matched', (string) count($report['migrated'])],
                ['Failed', (string) count($report['failed'])],
                ['Skipped', (string) count($report['skipped'])],
                ['SEO rewritten', (string) count($report['seo_rewritten'])],
            ]
        );

        foreach (array_slice($report['migrated'], 0, 15) as $row) {
            $this->line(sprintf(
                '  ✓ %s → %s',
                $row['name'] ?? '',
                $row['stored_path'] ?? ($row['fetch_url'] ?? $row['remote_url'] ?? '')
            ));
        }

        if (count($report['failed']) > 0) {
            $this->newLine();
            $this->warn('Failed examples:');
            foreach (array_slice($report['failed'], 0, 10) as $row) {
                $this->line('  - '.($row['name'] ?? '').': '.($row['reason'] ?? ''));
            }
        }

        $this->newLine();
        $this->info('Audit written: '.$path);

        if (! $execute) {
            $this->warn('Dry-run only. Pass --execute to upload images and update product_images.');
        }

        return self::SUCCESS;
    }
}
