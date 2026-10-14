<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;
use InvalidArgumentException;

class CsvCatalogueSourceReader implements CatalogueSourceReader
{
    public function __construct(
        protected string $path,
        protected string $sourceName = 'csv'
    ) {}

    public function sourceType(): string
    {
        return 'csv';
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function records(): iterable
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException('CSV catalogue source file not found: '.$this->path);
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to open CSV catalogue source: '.$this->path);
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers) || $headers === []) {
                throw new InvalidArgumentException('CSV catalogue source is missing a header row.');
            }

            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($row === [null] || $row === false) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $assoc[$header] = $row[$index] ?? null;
                }

                if ($this->rowIsEmpty($assoc)) {
                    continue;
                }

                if (isset($assoc['variants']) && is_string($assoc['variants']) && $assoc['variants'] !== '') {
                    $decoded = json_decode($assoc['variants'], true);
                    $assoc['variants'] = is_array($decoded) ? $decoded : [];
                }

                if (isset($assoc['images']) && is_string($assoc['images']) && $assoc['images'] !== '') {
                    $decoded = json_decode($assoc['images'], true);
                    $assoc['images'] = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode('|', $assoc['images']))));
                }

                $assoc['_source_row'] = $rowNumber;
                yield $assoc;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
