<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;
use InvalidArgumentException;
use RuntimeException;

class JsonCatalogueSourceReader implements CatalogueSourceReader
{
    public function __construct(
        protected string $path,
        protected string $sourceName = 'json'
    ) {}

    public function sourceType(): string
    {
        return 'json';
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function records(): iterable
    {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException('JSON catalogue source file not found: '.$this->path);
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read JSON catalogue source: '.$this->path);
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('JSON catalogue source must decode to an array.');
        }

        $items = array_is_list($decoded) ? $decoded : ($decoded['products'] ?? $decoded['records'] ?? null);
        if (! is_array($items)) {
            throw new InvalidArgumentException('JSON catalogue source must contain a list of products or a products/records key.');
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('JSON catalogue record #'.($index + 1).' must be an object.');
            }
            yield $item;
        }
    }
}
