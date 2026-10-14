<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;

class ArrayCatalogueSourceReader implements CatalogueSourceReader
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        protected array $records,
        protected string $sourceName = 'manual',
        protected string $sourceType = 'array'
    ) {}

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function records(): iterable
    {
        foreach ($this->records as $record) {
            yield $record;
        }
    }
}
