<?php

declare(strict_types=1);

namespace App\Contracts\Catalogue;

/**
 * Catalogue source that supports resumable pagination checkpoints.
 */
interface ResumableCatalogueSourceReader extends CatalogueSourceReader
{
    /**
     * @return array<string, mixed>
     */
    public function checkpoint(): array;

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    public function restore(array $checkpoint): void;

    /**
     * @return array<string, int|string|bool|null>
     */
    public function retrievalStats(): array;
}
