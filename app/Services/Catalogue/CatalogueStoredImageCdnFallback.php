<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

/**
 * Localized catalogue images live under /storage/product_images, which is
 * gitignored and therefore absent from Vercel container deploys. When the
 * local file is missing, fall back to the BunnyCDN remote URL recorded during
 * catalogue:localize-cdn-images so the storefront still shows product photos.
 */
class CatalogueStoredImageCdnFallback
{
    /** @var array<string, string>|null */
    private static ?array $map = null;

    public function remoteUrlForStoredPath(?string $storedPath): ?string
    {
        if (! is_string($storedPath) || $storedPath === '') {
            return null;
        }

        $map = $this->map();
        if ($map === []) {
            return null;
        }

        return $map[$storedPath]
            ?? $map[rawurldecode($storedPath)]
            ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $path = storage_path('app/catalogue-import/audits/cdn-localize.json');
        if (! is_file($path)) {
            return self::$map = [];
        }

        try {
            /** @var mixed $json */
            $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return self::$map = [];
        }

        if (! is_array($json)) {
            return self::$map = [];
        }

        $map = [];
        foreach ($json['localized'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $stored = $row['stored_url'] ?? null;
            $remote = $row['remote_url'] ?? null;
            if (! is_string($stored) || $stored === '' || ! is_string($remote) || $remote === '') {
                continue;
            }
            $map[$stored] = $remote;
        }

        return self::$map = $map;
    }
}
