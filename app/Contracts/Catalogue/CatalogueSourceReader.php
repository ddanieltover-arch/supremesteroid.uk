<?php

declare(strict_types=1);

namespace App\Contracts\Catalogue;

/**
 * Source-agnostic reader for catalogue onboarding records.
 *
 * @return iterable<int, array<string, mixed>>
 */
interface CatalogueSourceReader
{
    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function records(): iterable;

    public function sourceType(): string;

    public function sourceName(): string;
}
