<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Catalogue\CatalogueSourceReader;
use App\Services\Catalogue\Import\ArrayCatalogueSourceReader;
use App\Services\Catalogue\Import\CatalogueImportOrchestrator;
use App\Services\Catalogue\Import\CsvCatalogueSourceReader;
use App\Services\Catalogue\Import\JsonCatalogueSourceReader;
use App\Services\Catalogue\Import\ReferenceSiteCatalogueSourceReader;
use App\Services\Catalogue\Import\ReferenceSiteHttpClient;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use App\Support\QualityAssuranceDatabase;
use App\Support\QualityAssuranceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CatalogueImportCommand extends Command
{
    protected $signature = 'catalogue:import
        {path? : Path to a JSON or CSV source file}
        {--dry-run : Analyse and report without mutating products, variants, categories, brands, or inventory}
        {--source= : Logical source name override}
        {--sample : Import the bundled local QA sample (lawful wellness fixtures only)}
        {--batch= : Optional batch number override}
        {--limit=25 : Maximum records to stage in this batch (recommended 10–25)}';

    protected $description = 'Stage catalogue source records for review. Never auto-publishes.';

    protected $aliases = ['catalogue'];

    public function handle(CatalogueImportOrchestrator $orchestrator): int
    {
        try {
            QualityAssuranceGuard::assertSafeEnvironment();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        try {
            $reader = $this->resolveReader();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($reader instanceof ReferenceSiteCatalogueSourceReader) {
            $limit = min($limit, (int) config('catalogue.import.reference_site.max_eligible', 20));
            QualityAssuranceDatabase::bindIsolatedSqliteIfNeeded();
            try {
                QualityAssuranceGuard::assertSafeEnvironment();
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $connection = QualityAssuranceDatabase::describe();
            $this->line('Database: '.$connection['driver'].' / '.$connection['database']);
            if (str_contains(strtolower($connection['database']), 'neon')) {
                $this->error('Refusing to use a Neon database for reference imports.');

                return self::FAILURE;
            }
        }

        $this->info(($dryRun ? 'Dry-run' : 'Staging').' catalogue import from '.$reader->sourceName().' ('.$reader->sourceType().'), limit '.$limit);

        $report = $orchestrator->import(
            $reader,
            dryRun: $dryRun,
            batchNumber: $this->option('batch') ? (string) $this->option('batch') : null,
            limit: $limit,
        );

        foreach ($report->lines() as $line) {
            $this->line($line);
        }

        if ($report->rows !== []) {
            $this->newLine();
            $this->table(
                ['Reference', 'Name', 'Match', 'Workflow'],
                collect($report->rows)->map(fn (array $row) => [
                    $row['reference'] ?? '',
                    Str::limit((string) ($row['name'] ?? ''), 40),
                    $row['match_status'] ?? '',
                    $row['workflow_status'] ?? '',
                ])->all()
            );
        }

        if ($reader instanceof ReferenceSiteCatalogueSourceReader) {
            $this->newLine();
            foreach ($reader->discoveryLines() as $line) {
                $this->line($line);
            }

            if (! $dryRun && $report->batch) {
                $summary = $report->batch->summary ?? [];
                $summary['discovery'] = $reader->discoveryStats();
                $report->batch->summary = $summary;
                $report->batch->save();
            }
        }

        return self::SUCCESS;
    }

    protected function resolveReader(): CatalogueSourceReader
    {
        if ($this->option('source') === 'reference-site') {
            $base = rtrim((string) config('catalogue.import.reference_site.base_url', 'https://supremesteroids.co.uk'), '/');
            $host = (string) parse_url($base, PHP_URL_HOST);
            if ($host === '') {
                throw new InvalidArgumentException('Reference site base URL is invalid.');
            }

            return new ReferenceSiteCatalogueSourceReader(
                fetcher: new ReferenceSiteHttpClient(
                    allowedHost: $host,
                    delayMs: (int) config('catalogue.import.reference_site.delay_ms', 2000),
                    timeoutSeconds: (int) config('catalogue.import.reference_site.timeout_seconds', 20),
                ),
                detector: app(RestrictedProductDetector::class),
                baseUrl: $base,
                maxPages: (int) config('catalogue.import.reference_site.max_pages', 30),
                maxEligible: (int) config('catalogue.import.reference_site.max_eligible', 20),
            );
        }

        if ($this->option('sample')) {
            $path = base_path('storage/app/catalogue-import/qa-sample.json');
            if (! is_file($path)) {
                throw new InvalidArgumentException('QA sample file missing: '.$path);
            }

            return new JsonCatalogueSourceReader($path, (string) ($this->option('source') ?: 'qa-sample'));
        }

        $path = $this->argument('path');
        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException('Provide a source path or use --sample.');
        }

        $absolute = $this->absolutePath($path);
        $sourceName = (string) ($this->option('source') ?: basename($absolute));
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => new JsonCatalogueSourceReader($absolute, $sourceName),
            'csv' => new CsvCatalogueSourceReader($absolute, $sourceName),
            default => throw new InvalidArgumentException('Supported source types: json, csv.'),
        };
    }

    protected function absolutePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $fromBase = base_path($path);
        if (is_file($fromBase)) {
            return $fromBase;
        }

        throw new InvalidArgumentException('Source file not found: '.$path);
    }
}
