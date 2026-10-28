<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalogue\CataloguePresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CatalogueCdnImageLocalizeService
{
    public function __construct(
        protected CataloguePresentation $presentation,
        protected int $delayMs = 400,
        protected int $timeoutSeconds = 25,
    ) {}

    /**
     * @return array{
     *   candidates: int,
     *   localized: list<array<string, mixed>>,
     *   failed: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   dry_run: bool
     * }
     */
    public function localize(bool $execute, ?int $limit = null): array
    {
        $candidates = $this->cdnImages($limit);
        $localized = [];
        $failed = [];
        $skipped = [];

        foreach ($candidates as $image) {
            /** @var ProductImage $image */
            $product = $image->product;
            $remoteUrl = (string) $image->url;
            $fetchUrl = $this->encodeRemoteUrl($remoteUrl);

            if ($product === null) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'url' => $remoteUrl,
                    'reason' => 'missing_product',
                ];
                continue;
            }

            if (! $this->isAllowedCdnHost($fetchUrl)) {
                $skipped[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'url' => $remoteUrl,
                    'reason' => 'host_not_allowed',
                ];
                continue;
            }

            if (! $execute) {
                $localized[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'fetch_url' => $fetchUrl,
                    'status' => 'dry_run',
                ];
                continue;
            }

            try {
                $this->pace();
                $storedPath = $this->downloadToPublicDisk($product, $image, $fetchUrl);
                $image->url = $storedPath;
                $image->thumbnail_url = $storedPath;
                $image->alt_text = $image->alt_text ?: $product->name;
                $image->save();

                $localized[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'stored_url' => $storedPath,
                    'status' => 'localized',
                ];
            } catch (\Throwable $exception) {
                $failed[] = [
                    'image_id' => $image->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'remote_url' => $remoteUrl,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'candidates' => $candidates->count(),
            'localized' => $localized,
            'failed' => $failed,
            'skipped' => $skipped,
            'dry_run' => ! $execute,
        ];
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function cdnImages(?int $limit = null): Collection
    {
        $hosts = $this->presentation->unusableImageHosts();
        if ($hosts === []) {
            $hosts = ['supremestd.b-cdn.net', 'supremesteroids.co.uk'];
        }

        $query = ProductImage::query()
            ->whereHas('product', fn ($product) => $product->purchasable())
            ->where(function ($builder) use ($hosts): void {
                foreach ($hosts as $index => $host) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}('url', 'like', '%'.$host.'%');
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

    protected function downloadToPublicDisk(Product $product, ProductImage $image, string $remoteUrl): string
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(['User-Agent' => 'SupremeSteroidsCatalogueLocalize/1.0'])
            ->withOptions(['verify' => false])
            ->get($remoteUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Image download failed HTTP '.$response->status());
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) < 500) {
            throw new RuntimeException('Image download too small.');
        }

        $mime = strtolower((string) ($response->header('Content-Type') ?: ''));
        if ($mime !== '' && ! str_starts_with($mime, 'image/') && ! str_contains($mime, 'octet-stream')) {
            throw new RuntimeException('Non-image content type: '.$mime);
        }

        $extension = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            default => 'jpg',
        };
        if (preg_match('#\.(png|jpe?g|webp|gif)(?:\?|$)#i', $remoteUrl, $extMatch) === 1) {
            $extension = strtolower($extMatch[1]) === 'jpeg' ? 'jpg' : strtolower($extMatch[1]);
        }

        $relative = 'product_images/'.$product->id.'/'.$image->id.'.'.$extension;
        Storage::disk('public')->put($relative, $bytes);

        return '/storage/'.$relative;
    }

    protected function isAllowedCdnHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $allowed = $this->presentation->unusableImageHosts();
        if ($allowed === []) {
            $allowed = ['supremestd.b-cdn.net', 'supremesteroids.co.uk'];
        }

        foreach ($allowed as $allowedHost) {
            $allowedHost = strtolower($allowedHost);
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
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
