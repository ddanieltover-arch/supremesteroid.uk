<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CatalogueBrokenImageRepairService
{
    /** @var list<string> */
    protected array $nonProductShopSlugs = [
        'account', 'cart', 'checkout', 'checkout-gate', 'login', 'register', 'forgot-password',
        'contact', 'order-success', 'order-failed', 'paygate-return', 'survey', 'wishlist',
    ];

    public function __construct(
        protected PublicProductPageParser $parser,
        protected int $delayMs = 750,
        protected int $timeoutSeconds = 25,
    ) {}

    /**
     * @return array{
     *   base_url: string,
     *   broken_count: int,
     *   source_products: int,
     *   repaired: list<array<string, mixed>>,
     *   unmatched: list<array<string, mixed>>,
     *   skipped: list<array<string, mixed>>,
     *   dry_run: bool
     * }
     */
    public function repair(string $baseUrl, bool $execute, ?int $limit = null): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($host === '') {
            throw new RuntimeException('Invalid base URL.');
        }

        $broken = $this->brokenProducts($limit);
        $sourceIndex = $this->discoverSourceProducts($baseUrl, $host);

        $repaired = [];
        $unmatched = [];
        $skipped = [];

        foreach ($broken as $row) {
            /** @var Product $product */
            $product = $row['product'];
            /** @var ProductImage $image */
            $image = $row['image'];
            $match = $this->matchSource($product, $sourceIndex);

            if ($match === null) {
                $unmatched[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'broken_url' => $image->url,
                    'reason' => 'no_confident_match',
                ];
                continue;
            }

            if (! $execute) {
                $repaired[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'broken_url' => $image->url,
                    'source_url' => $match['url'],
                    'source_title' => $match['title'],
                    'match' => $match['match_type'],
                    'score' => $match['score'],
                    'status' => 'dry_run_matched',
                ];
                continue;
            }

            try {
                $this->pace();
                $html = $this->fetch($match['url'], $host);
                $parsed = $this->parser->parse($html, $match['url']);
                $remoteImage = $this->pickImageUrl($parsed, $html, $baseUrl);
                if ($remoteImage === null) {
                    $skipped[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'source_url' => $match['url'],
                        'reason' => 'no_image_on_source_page',
                    ];
                    continue;
                }

                $brokenUrl = $row['broken_url'];
                $this->pace();
                $storedPath = $this->downloadToPublicDisk($product, $remoteImage, $host);
                $image->url = $storedPath;
                $image->thumbnail_url = $storedPath;
                $image->alt_text = $image->alt_text ?: $product->name;
                $image->save();

                $repaired[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'broken_url' => $brokenUrl,
                    'source_url' => $match['url'],
                    'source_image' => $remoteImage,
                    'stored_url' => $storedPath,
                    'match' => $match['match_type'],
                    'score' => $match['score'],
                    'status' => 'repaired',
                ];
            } catch (\Throwable $exception) {
                $skipped[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'source_url' => $match['url'],
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'base_url' => $baseUrl,
            'broken_count' => $broken->count(),
            'source_products' => count($sourceIndex),
            'repaired' => $repaired,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'dry_run' => ! $execute,
        ];
    }

    /**
     * @return Collection<int, array{product: Product, image: ProductImage, broken_url: string}>
     */
    public function brokenProducts(?int $limit = null): Collection
    {
        $query = Product::query()
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('display_order')])
            ->orderBy('name');

        $rows = collect();
        foreach ($query->cursor() as $product) {
            /** @var Product $product */
            $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
            if (! $image instanceof ProductImage) {
                continue;
            }

            $url = (string) $image->url;
            if ($url === '') {
                continue;
            }

            $isSuspectHost = str_contains(strtolower($url), 'supremestd.b-cdn.net')
                || str_contains(strtolower($url), 'supremesteroids.co.uk');

            if (! $isSuspectHost) {
                continue;
            }

            // Only repair URLs that actually fail to load.
            if ($this->remoteUrlLooksHealthy($url)) {
                continue;
            }

            $rows->push([
                'product' => $product,
                'image' => $image,
                'broken_url' => $url,
            ]);

            if ($limit !== null && $rows->count() >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{url: string, slug: string, title: string, tokens: list<string>}>
     */
    protected function discoverSourceProducts(string $baseUrl, string $host): array
    {
        $this->pace();
        $sitemap = $this->fetch($baseUrl.'/sitemap.xml', $host);
        $urls = [];
        if (preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $sitemap, $matches) > 0) {
            foreach ($matches[1] as $loc) {
                $loc = trim(html_entity_decode($loc, ENT_QUOTES));
                if ($this->isProductUrl($loc, $host)) {
                    $urls[] = $loc;
                }
            }
        }

        $urls = array_values(array_unique($urls));
        $index = [];
        foreach ($urls as $url) {
            $slug = $this->productSlugFromUrl($url);
            if ($slug === null) {
                continue;
            }
            $title = Str::title(str_replace('-', ' ', $slug));
            $tokens = $this->tokens($title.' '.$slug);
            $index[] = [
                'url' => $url,
                'slug' => $slug,
                'title' => $title,
                'tokens' => $tokens,
            ];
        }

        return $index;
    }

    /**
     * @param  list<array{url: string, slug: string, title: string, tokens: list<string>}>  $sourceIndex
     * @return array{url: string, slug: string, title: string, match_type: string, score: float}|null
     */
    protected function matchSource(Product $product, array $sourceIndex): ?array
    {
        $productSlug = strtolower((string) $product->slug);
        $productTokens = $this->tokens(($product->name ?? '').' '.$productSlug.' '.($product->brand?->name ?? ''));

        $best = null;
        $bestScore = 0.0;
        $bestType = 'none';

        foreach ($sourceIndex as $candidate) {
            if ($candidate['slug'] === $productSlug) {
                return [
                    'url' => $candidate['url'],
                    'slug' => $candidate['slug'],
                    'title' => $candidate['title'],
                    'match_type' => 'exact_slug',
                    'score' => 1.0,
                ];
            }

            if ($product->sku && ! $product->isInternalSku()) {
                $skuToken = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $product->sku) ?? '');
                $slugCompact = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate['slug']) ?? '');
                if ($skuToken !== '' && str_contains($slugCompact, $skuToken)) {
                    return [
                        'url' => $candidate['url'],
                        'slug' => $candidate['slug'],
                        'title' => $candidate['title'],
                        'match_type' => 'sku_in_slug',
                        'score' => 0.95,
                    ];
                }
            }

            $score = $this->tokenOverlap($productTokens, $candidate['tokens']);
            // Require meaningful overlap: strength/compound tokens usually 3+
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
                $bestType = 'token_overlap';
            }
        }

        if ($best === null || $bestScore < 0.55) {
            return null;
        }

        // Guard against weak matches on short names
        $shared = count(array_intersect($productTokens, $best['tokens']));
        if ($shared < 2) {
            return null;
        }

        return [
            'url' => $best['url'],
            'slug' => $best['slug'],
            'title' => $best['title'],
            'match_type' => $bestType,
            'score' => round($bestScore, 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    protected function pickImageUrl(array $parsed, string $html, string $baseUrl): ?string
    {
        $candidates = array_map('strval', (array) ($parsed['images'] ?? []));

        if (preg_match('#property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $og) === 1
            || preg_match('#content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $og) === 1) {
            array_unshift($candidates, html_entity_decode(trim($og[1]), ENT_QUOTES));
        }

        foreach ($candidates as $src) {
            $src = trim($src);
            if ($src === '') {
                continue;
            }
            if (str_starts_with($src, '//')) {
                $src = 'https:'.$src;
            } elseif (str_starts_with($src, '/')) {
                $src = $baseUrl.$src;
            }
            $lower = strtolower($src);
            if (str_contains($lower, 'logo') || str_contains($lower, 'icon') || str_contains($lower, 'lab_tested')) {
                continue;
            }
            if (! preg_match('#\.(?:jpe?g|png|webp|gif)(?:\?|$)#i', $src) && ! str_contains($lower, '/media/products/')) {
                continue;
            }

            return $src;
        }

        return null;
    }

    protected function downloadToPublicDisk(Product $product, string $remoteUrl, string $allowedHost): string
    {
        $remoteHost = strtolower((string) parse_url($remoteUrl, PHP_URL_HOST));
        if ($remoteHost === '' || ($remoteHost !== $allowedHost && ! str_ends_with($remoteHost, '.'.$allowedHost))) {
            // Allow same-site media on steroids-uk.com only
            if ($remoteHost !== $allowedHost) {
                throw new RuntimeException('Refusing image host: '.$remoteHost);
            }
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders(['User-Agent' => 'SupremeSteroidsCatalogueReview/1.0'])
            ->withOptions(['verify' => false])
            ->get($remoteUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Image download failed HTTP '.$response->status());
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) < 500) {
            throw new RuntimeException('Image download too small.');
        }

        $mime = (string) ($response->header('Content-Type') ?: '');
        $extension = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            default => 'jpg',
        };
        if (preg_match('#\.(png|jpe?g|webp|gif)(?:\?|$)#i', $remoteUrl, $extMatch) === 1) {
            $extension = strtolower($extMatch[1]) === 'jpeg' ? 'jpg' : strtolower($extMatch[1]);
        }

        $relative = 'product_images/'.$product->id.'/primary.'.$extension;
        Storage::disk('public')->put($relative, $bytes);

        return '/storage/'.$relative;
    }

    protected function isProductUrl(string $url, string $host): bool
    {
        $parts = parse_url($url);
        if (($parts['host'] ?? '') !== $host) {
            return false;
        }
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (! str_starts_with($path, 'shop/')) {
            return false;
        }
        $rest = substr($path, strlen('shop/'));
        if ($rest === '' || str_contains($rest, '/')) {
            return false;
        }
        $slug = strtolower($rest);

        return ! in_array($slug, $this->nonProductShopSlugs, true);
    }

    protected function productSlugFromUrl(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (! str_starts_with($path, 'shop/')) {
            return null;
        }
        $slug = substr($path, strlen('shop/'));
        if ($slug === '' || str_contains($slug, '/')) {
            return null;
        }

        return strtolower($slug);
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $value): array
    {
        $value = strtolower(html_entity_decode($value, ENT_QUOTES));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $stop = [
            'the', 'and', 'for', 'with', 'labs', 'lab', 'pharma', 'tablets', 'tabs', 'mg', 'ml',
            'injectable', 'oral', 'steroid', 'steroids', 'uk', 'official', 'product',
        ];
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 2 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    protected function tokenOverlap(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $shared = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $shared / $union : 0.0;
    }

    protected function remoteUrlLooksHealthy(string $url): bool
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SupremeSteroidsCatalogueReview/1.0'])
                ->withOptions(['verify' => false])
                ->head($url);
            if ($response->successful()) {
                return true;
            }
            // Some CDNs reject HEAD; try GET range-less lightweight check
            $get = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SupremeSteroidsCatalogueReview/1.0'])
                ->withOptions(['verify' => false])
                ->get($url);

            return $get->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function fetch(string $url, string $allowedHost): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== $allowedHost) {
            throw new RuntimeException('Refusing fetch host: '.$host);
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => 'SupremeSteroidsCatalogueReview/1.0 (local catalogue repair)',
                'Accept' => 'text/html,application/xml,text/xml;q=0.9,*/*;q=0.8',
            ])
            ->withOptions(['verify' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' for '.$url);
        }

        return $response->body();
    }

    protected function pace(): void
    {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
    }
}
