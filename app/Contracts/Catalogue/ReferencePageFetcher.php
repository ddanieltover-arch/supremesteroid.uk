<?php

declare(strict_types=1);

namespace App\Contracts\Catalogue;

use RuntimeException;

interface ReferencePageFetcher
{
    /**
     * @throws RuntimeException When the page cannot be fetched
     */
    public function get(string $url): string;
}
