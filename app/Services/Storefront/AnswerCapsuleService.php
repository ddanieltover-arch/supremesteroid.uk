<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * GEO-friendly “Quick answer” blurbs for storefront pages.
 * Keep factual and non-prescriptive — no medical or dosing claims.
 */
class AnswerCapsuleService
{
    public function fromConfig(string $key, string $fallback = ''): string
    {
        $value = trim((string) config("homepage.answer_capsules.{$key}", $fallback));

        return $this->limit($value !== '' ? $value : $fallback);
    }

    public function home(): string
    {
        return $this->fromConfig(
            'home',
            'Supreme Steroids is a UK online storefront listing compliance-reviewed catalogue products, brands, and educational guides. Shop published items with clear specifications, UK shipping options, and discreet fulfilment — without medical advice or dosing protocols.'
        );
    }

    public function shop(): string
    {
        return $this->fromConfig(
            'shop',
            'The Supreme Steroids shop catalogues published UK-available products across brands and categories. Use filters to browse purchasable listings with server-confirmed pricing, stock status, and product details before checkout.'
        );
    }

    public function category(Category|string $category): string
    {
        $name = $category instanceof Category ? (string) $category->name : trim($category);

        $template = (string) config(
            'homepage.answer_capsules.category',
            '{name} products are listed in the Supreme Steroids UK catalogue. Browse published items in this category with clear specifications, pricing, and stock status before checkout.'
        );

        return $this->limit($this->fill($template, ['name' => $name !== '' ? $name : 'Category']));
    }

    public function brand(Brand|string $brand): string
    {
        $name = $brand instanceof Brand ? (string) $brand->name : trim($brand);

        $template = (string) config(
            'homepage.answer_capsules.brand',
            '{name} products are available from Supreme Steroids UK. Shop published {name} catalogue listings with server-confirmed pricing, stock status, and product details.'
        );

        return $this->limit($this->fill($template, ['name' => $name !== '' ? $name : 'Brand']));
    }

    public function search(?string $term = null): string
    {
        $term = trim((string) $term);
        if ($term !== '') {
            $template = (string) config(
                'homepage.answer_capsules.search_term',
                'Search results for “{term}” from the Supreme Steroids UK catalogue. Refine by brand, category, price, and availability to find published purchasable listings.'
            );

            return $this->limit($this->fill($template, ['term' => $term]));
        }

        return $this->fromConfig(
            'search',
            'Search the Supreme Steroids UK catalogue for published products. Use filters to refine by brand, category, price, and availability before checkout.'
        );
    }

    public function listing(string $kind, ?object $context = null, ?string $heading = null, ?string $searchTerm = null): string
    {
        return match ($kind) {
            'category' => $this->category(
                $context instanceof Category ? $context : (string) ($heading ?? 'Category')
            ),
            'brand' => $this->brand(
                $context instanceof Brand ? $context : (string) ($heading ?? 'Brand')
            ),
            'search' => $this->search($searchTerm),
            default => $this->shop(),
        };
    }

    public function product(Product $product, ?string $stored = null): string
    {
        $stored = trim((string) $stored);
        if ($stored !== '') {
            return $this->limit($stored);
        }

        $name = trim((string) $product->name) ?: 'This product';
        $brandName = trim((string) ($product->brand?->name ?? 'the listed brand')) ?: 'the listed brand';
        $categoryName = trim((string) ($product->category?->name ?? 'catalogue')) ?: 'catalogue';

        $template = (string) config(
            'homepage.answer_capsules.product',
            '{name} is a {category} catalogue listing from {brand} with commercial specifications shown on this page; it does not include medical or dosing advice.'
        );

        return $this->limit($this->fill($template, [
            'name' => $name,
            'brand' => $brandName,
            'category' => $categoryName,
        ]));
    }

    public function blogIndex(): string
    {
        return $this->fromConfig(
            'blog',
            'Educational guides and reference articles from Supreme Steroids UK covering catalogue topics, shipping, and store information — without medical or dosing advice.'
        );
    }

    public function blogPost(string $title, ?string $excerpt = null): string
    {
        $excerpt = trim(strip_tags((string) $excerpt));
        if ($excerpt !== '') {
            return $this->limit($excerpt);
        }

        $template = (string) config(
            'homepage.answer_capsules.blog_post',
            '{title} is an educational article from Supreme Steroids UK. It provides storefront and catalogue context without medical or dosing advice.'
        );

        return $this->limit($this->fill($template, [
            'title' => trim($title) !== '' ? trim($title) : 'This article',
        ]));
    }

    public function contentPage(string $title, ?string $contentPlain = null): string
    {
        $template = (string) config(
            'homepage.answer_capsules.content',
            '{title} — official Supreme Steroids UK store information. Review published policies and guidance for shopping, shipping, and account support.'
        );

        $filled = $this->fill($template, [
            'title' => trim($title) !== '' ? trim($title) : 'This page',
        ]);

        // Prefer a short first-sentence excerpt when page body exists; otherwise use the template.
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $contentPlain)) ?? '');
        if ($plain !== '') {
            $sentence = Str::before($plain, '. ');
            if ($sentence !== '' && $sentence !== $plain) {
                $excerpt = rtrim($sentence, '.').'.';
                if (strlen($excerpt) >= 40 && strlen($excerpt) <= 280) {
                    return $this->limit($excerpt);
                }
            }
        }

        return $this->limit($filled);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function fill(string $template, array $replacements): string
    {
        $out = $template;
        foreach ($replacements as $key => $value) {
            $out = str_replace('{'.$key.'}', $value, $out);
        }

        return trim($out);
    }

    protected function limit(string $text, int $max = 320): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        return Str::limit($text, $max, '');
    }
}
