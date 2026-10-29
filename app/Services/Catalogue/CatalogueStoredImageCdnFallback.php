<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

/**
 * Localized catalogue images live under /storage/product_images, which is
 * gitignored and therefore absent from Vercel container deploys. Fall back to
 * the BunnyCDN remote URL recorded during catalogue:localize-cdn-images.
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

        foreach ($this->candidatePaths() as $path) {
            $loaded = $this->loadMapFromPath($path);
            if ($loaded !== null) {
                return self::$map = $loaded;
            }
        }

        return self::$map = [];
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(): array
    {
        return [
            // Compact map shipped with the app (preferred for container deploys).
            resource_path('catalogue/cdn-stored-to-remote.json'),
            // Full localize audit kept for local/ops use.
            storage_path('app/catalogue-import/audits/cdn-localize.json'),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function loadMapFromPath(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            /** @var mixed $json */
            $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($json)) {
            return null;
        }

        // Compact map: { "/storage/...": "https://..." }
        $firstValue = reset($json);
        if (is_string($firstValue)) {
            $map = [];
            foreach ($json as $stored => $remote) {
                if (is_string($stored) && is_string($remote) && $stored !== '' && $remote !== '') {
                    $map[$stored] = $remote;
                }
            }

            return $map === [] ? null : $map;
        }

        // Full audit: { localized: [ { stored_url, remote_url } ] }
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

        return $map === [] ? null : $map;
    }
}
