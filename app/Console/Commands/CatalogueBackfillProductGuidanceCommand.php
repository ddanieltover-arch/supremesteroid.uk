<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductContent;
use App\Services\Catalogue\OriginalProductCopyService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CatalogueBackfillProductGuidanceCommand extends Command
{
    protected $signature = 'catalogue:backfill-product-guidance
        {--sku= : Limit to one SKU}
        {--dry-run : Report changes without writing}
        {--limit=0 : Maximum products to process (0 = all)}';

    protected $description = 'Fill empty product guidance fields (why/how/where and related) from compliance-safe templates without overwriting admin edits.';

    public function handle(OriginalProductCopyService $copy): int
    {
        $query = Product::query()
            ->with(['content', 'brand', 'category', 'attributes'])
            ->whereHas('content')
            ->orderBy('sku');

        if ($sku = $this->option('sku')) {
            $query->where('sku', $sku);
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->warn('No products with content rows matched.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        $publishedPosts = BlogPost::query()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'slug']);

        foreach ($products as $product) {
            /** @var ProductContent|null $content */
            $content = $product->content;
            if (! $content) {
                $skipped++;

                continue;
            }

            $normalized = $copy->normalizedFromProduct($product);
            $composed = $copy->compose($normalized, $product->brand, $product->category);

            $payload = [];
            foreach ($this->fillableGuidanceKeys() as $key) {
                if (! $this->isEmptyGuidanceValue($content->{$key} ?? null)) {
                    continue;
                }

                $value = $composed[$key] ?? null;
                if ($key === 'related_reading') {
                    $value = $this->matchRelatedReading($product, $publishedPosts);
                }

                if ($this->isEmptyGuidanceValue($value)) {
                    continue;
                }

                $payload[$key] = $value;
            }

            if ($payload === []) {
                $skipped++;

                continue;
            }

            $updated++;
            $this->line(($dryRun ? '[dry-run] ' : '').$product->sku.': '.implode(', ', array_keys($payload)));

            if (! $dryRun) {
                $content->fill($payload);
                $content->save();
            }
        }

        $this->info(($dryRun ? 'Dry run: ' : '')."Updated {$updated}, skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function fillableGuidanceKeys(): array
    {
        return [
            'why_choose',
            'how_supplied',
            'where_it_fits',
            'who_its_for',
            'what_you_get',
            'key_differences',
            'answer_capsule',
            'related_reading',
        ];
    }

    protected function isEmptyGuidanceValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * @param  Collection<int, BlogPost>  $posts
     * @return list<array{slug: string, title: string}>
     */
    protected function matchRelatedReading(Product $product, Collection $posts): array
    {
        if ($posts->isEmpty()) {
            return [];
        }

        $needles = array_values(array_filter([
            $product->category?->name,
            $product->brand?->name,
            $product->name,
        ]));

        if ($needles === []) {
            return [];
        }

        $matched = $posts->filter(function (BlogPost $post) use ($needles) {
            $haystack = mb_strtolower($post->title.' '.$post->slug);

            foreach ($needles as $needle) {
                $parts = preg_split('#[\s\-_\/]+#', mb_strtolower((string) $needle)) ?: [];
                foreach ($parts as $part) {
                    if (mb_strlen($part) < 4) {
                        continue;
                    }
                    if (str_contains($haystack, $part)) {
                        return true;
                    }
                }
            }

            return false;
        })->take(3);

        return $matched->map(fn (BlogPost $post) => [
            'slug' => $post->slug,
            'title' => $post->title,
        ])->values()->all();
    }
}
