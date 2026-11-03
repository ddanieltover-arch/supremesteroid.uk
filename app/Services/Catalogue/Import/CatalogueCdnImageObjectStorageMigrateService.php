<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SeoMetadata;
use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\CatalogueStoredImageCdnFallback;
use App\Services\Storage\ObjectStorageService;
use App\Services\Storefront\HomepageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CatalogueCdnImageObjectStorageMigrateService
{
    public function __construct(
        protected CataloguePresentation $presentation,
        protected ObjectStorageService $storage,
        protected CatalogueStoredImageCdnFallback $cdnFallback,
        protected int $delayMs = 400,
        protected int $timeoutSeconds = 25,
    ) {}

    /**
     * @return array{
     *   candidates: int,
     *   migrated: list<array<string, mixed>>,
     *   failed: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   seo_rewritten: list<array<string, mixed>>,
     *   dry_run: bool
     * }
     */
    public function migrate(bool $execute, ?int $limit = null): array
    {
        $candidates = $this->candidateImages($limit);
        $migrated = [];
        $failed = [];
        $skipped = [];
        $affectedSlugs = [];

        foreach ($candidates as $image) {
            /** @var ProductImage $image */
            $product = $image->product;
            $currentUrl = (string) $image->url;

            if ($product === null) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'url' => $currentUrl,
                    'reason' => 'missing_product',
                ];
                continue;
            }

            if ($this->isAlreadyFirstParty($currentUrl)) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'url' => $currentUrl,
                    'reason' => 'already_first_party',
                ];
                continue;
            }

            $remoteUrl = $this->resolveRemoteUrl($currentUrl);
            if ($remoteUrl === null) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'url' => $currentUrl,
                    'reason' => 'unresolvable_remote',
                ];
                continue;
            }

            $fetchUrl = $this->encodeRemoteUrl($remoteUrl);
            if (! $this->isAllowedCdnHost($fetchUrl)) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'url' => $currentUrl,
                    'remote_url' => $remoteUrl,
                    'reason' => 'host_not_allowed',
                ];
                continue;
            }

            if (! $execute) {
                $migrated[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'fetch_url' => $fetchUrl,
                    'source_url' => $currentUrl,
                    'status' => 'dry_run',
                ];
                continue;
            }

            try {
                $this->pace();
                [$contents, $filename, $mime, $extension] = $this->downloadBinary($fetchUrl);

                $stored = $this->storage->storeValidatedBinary(
                    contents: $contents,
                    originalFilename: $filename,
                    mimeType: $mime,
                    sizeBytes: strlen($contents),
                    extension: $extension,
                    collection: (string) config('filesystems.uploads.product_images_collection', 'product_images'),
                    uploader: null,
                    fileable: $product,
                );

                $image->url = $stored->path;
                if (filled($image->thumbnail_url)) {
                    $image->thumbnail_url = $stored->path;
                }
                $image->alt_text = $image->alt_text ?: $product->name;
                $image->save();

                $affectedSlugs[$product->slug] = true;

                $migrated[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'source_url' => $currentUrl,
                    'stored_path' => $stored->path,
                    'disk' => $stored->disk,
                    'public_url' => $image->fresh()->publicUrl(),
                    'status' => 'migrated',
                ];
            } catch (\Throwable $exception) {
                $failed[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'source_url' => $currentUrl,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        $seoRewritten = [];
        if ($execute) {
            $seoRewritten = $this->rewriteBunnyOgImageUrls();
            $this->bustImageCaches(array_keys($affectedSlugs));
        }

        return [
            'candidates' => $candidates->count(),
            'migrated' => $migrated,
            'failed' => $failed,
            'skipped' => $skipped,
            'seo_rewritten' => $seoRewritten,
            'dry_run' => ! $execute,
        ];
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function candidateImages(?int $limit = null): Collection
    {
        $hosts = $this->cdnHosts();

        $query = ProductImage::query()
            ->whereHas('product', fn ($product) => $product->purchasable())
            ->where(function ($builder) use ($hosts): void {
                $builder->where('url', 'like', '/storage/product_images/%');
                foreach ($hosts as $host) {
                    $builder->orWhere('url', 'like', '%'.$host.'%');
                }
            })
            ->with(['product:id,name,slug,sku,status,classification,compliance_verified_at,price_amount,is_demo'])
            ->orderBy('product_id')
            ->orderByDesc('is_primary')
            ->orderBy('display_order');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Rewrite product seo_metadata.og_image_url values that still hotlink BunnyCDN.
     *
     * @return list<array<string, mixed>>
     */
    public function rewriteBunnyOgImageUrls(): array
    {
        $hosts = $this->cdnHosts();
        $rewritten = [];

        $records = SeoMetadata::query()
            ->where('seoable_type', Product::class)
            ->whereNotNull('og_image_url')
            ->where(function ($builder) use ($hosts): void {
                foreach ($hosts as $index => $host) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}('og_image_url', 'like', '%'.$host.'%');
                }
            })
            ->get();

        foreach ($records as $seo) {
            /** @var SeoMetadata $seo */
            $product = Product::query()->with('images')->find($seo->seoable_id);
            $before = $seo->og_image_url;
            $primary = $product?->images->firstWhere('is_primary', true) ?? $product?->images->first();
            $after = $primary?->publicUrl();

            // Prefer first-party publicUrl; clear Bunny hotlink if unresolvable so SeoService can derive later.
            if (is_string($after) && $after !== '' && ! $this->urlContainsCdnHost($after)) {
                $seo->og_image_url = $after;
            } else {
                $seo->og_image_url = null;
            }
            $seo->save();

            if ($product?->slug) {
                Cache::forget('catalogue.product.detail.v2.'.$product->slug);
            }

            $rewritten[] = [
                'seo_metadata_id' => $seo->id,
                'product_id' => $seo->seoable_id,
                'slug' => $product?->slug,
                'before' => $before,
                'after' => $seo->og_image_url,
            ];
        }

        return $rewritten;
    }

    /**
     * @param  list<string>  $slugs
     */
    public function bustImageCaches(array $slugs): void
    {
        foreach ($slugs as $slug) {
            if ($slug !== '') {
                Cache::forget('catalogue.product.detail.v2.'.$slug);
            }
        }

        Cache::forget('catalogue.nav.v2');
        Cache::forget('catalogue.nav.highlights.v2');
        Cache::forget('catalogue.navigation.v4');
        Cache::forget('catalogue.navigation.highlights.v1');

        app(HomepageService::class)->flush();
    }

    protected function resolveRemoteUrl(string $currentUrl): ?string
    {
        if (str_starts_with($currentUrl, '/storage/')) {
            $remote = $this->cdnFallback->remoteUrlForStoredPath($currentUrl);

            return is_string($remote) && $remote !== '' ? $remote : null;
        }

        if (preg_match('#^https?://#i', $currentUrl) === 1) {
            return $currentUrl;
        }

        return null;
    }

    protected function isAlreadyFirstParty(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // Object-storage key written by ObjectStorageService / ApproveCatalogueImageAction.
        if (preg_match('#^product_images/\d{4}/\d{2}/\d{2}/[0-9a-f\-]+\.[a-z0-9]+$#i', $url) === 1) {
            return true;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ($this->firstPartyHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function firstPartyHosts(): array
    {
        $hosts = [];
        foreach ([
            config('filesystems.disks.s3.url'),
            config('filesystems.disks.r2.url'),
            config('app.url'),
        ] as $base) {
            if (! is_string($base) || $base === '') {
                continue;
            }
            $host = strtolower((string) parse_url($base, PHP_URL_HOST));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        $hosts[] = 'assets.supremesteroid.uk';
        $hosts[] = 'supremesteroid.uk';

        return array_values(array_unique($hosts));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function downloadBinary(string $remoteUrl): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(['User-Agent' => 'SupremeSteroidsCatalogueObjectStorageMigrate/1.0'])
            ->withOptions(['verify' => false])
            ->get($remoteUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Image download failed HTTP '.$response->status());
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) < 500) {
            throw new RuntimeException('Image download too small.');
        }

        $headerMime = strtolower((string) ($response->header('Content-Type') ?: ''));
        if ($headerMime !== '' && ! str_starts_with($headerMime, 'image/') && ! str_contains($headerMime, 'octet-stream')) {
            throw new RuntimeException('Non-image content type: '.$headerMime);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower((string) ($finfo->buffer($bytes) ?: ''));
        $mime = match (true) {
            str_starts_with($detected, 'image/') => $detected,
            str_starts_with($headerMime, 'image/') => explode(';', $headerMime)[0],
            default => 'image/jpeg',
        };

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        if ($extension === 'gif') {
            throw new RuntimeException('GIF images are not allowed in object storage uploads; convert to jpg/png/webp first.');
        }

        if (preg_match('#\.(png|jpe?g|webp)(?:\?|$)#i', $remoteUrl, $extMatch) === 1) {
            $extension = strtolower($extMatch[1]) === 'jpeg' ? 'jpg' : strtolower($extMatch[1]);
            $mime = match ($extension) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
        }

        $rawBaseName = basename((string) (parse_url($remoteUrl, PHP_URL_PATH) ?: 'image.'.$extension));
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $rawBaseName) ?: 'image.'.$extension;

        return [$bytes, $filename, $mime, $extension];
    }

    protected function isAllowedCdnHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach ($this->cdnHosts() as $allowedHost) {
            $allowedHost = strtolower($allowedHost);
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    protected function urlContainsCdnHost(string $url): bool
    {
        $normalized = strtolower($url);
        foreach ($this->cdnHosts() as $host) {
            if (str_contains($normalized, strtolower($host))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function cdnHosts(): array
    {
        $hosts = $this->presentation->unusableImageHosts();
        if ($hosts === []) {
            $hosts = ['supremestd.b-cdn.net', 'supremesteroids.co.uk'];
        }

        return $hosts;
    }

    protected function encodeRemoteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return str_replace(' ', '%20', $url);
        }

        $path = isset($parts['path'])
            ? implode('/', array_map(
                static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
                explode('/', $parts['path'])
            ))
            : '';

        $encoded = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $encoded .= ':'.$parts['port'];
        }
        $encoded .= $path;
        if (isset($parts['query'])) {
            $encoded .= '?'.$parts['query'];
        }
        if (isset($parts['fragment'])) {
            $encoded .= '#'.$parts['fragment'];
        }

        return $encoded;
    }

    protected function pace(): void
    {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
    }
}
