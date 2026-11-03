<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [];

        $urls[] = $this->entry(route('home'), now(), 'daily', '1.0');
        $urls[] = $this->entry(route('shop.index'), now(), 'daily', '0.9');
        $urls[] = $this->entry(route('blog.index'), now(), 'weekly', '0.7');

        Product::query()
            ->purchasable()
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(function (Product $product) use (&$urls): void {
                $lastmod = $product->updated_at ?? $product->published_at ?? now();
                $urls[] = $this->entry(
                    route('catalogue.show', $product->slug),
                    $lastmod,
                    'weekly',
                    '0.8'
                );
            });

        Category::query()
            ->where('is_visible', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Category $category) use (&$urls): void {
                $urls[] = $this->entry(
                    route('category.show', $category->slug),
                    $category->updated_at ?? now(),
                    'weekly',
                    '0.7'
                );
            });

        Brand::query()
            ->where('is_active', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->each(function (Brand $brand) use (&$urls): void {
                $urls[] = $this->entry(
                    route('brand.show', $brand->slug),
                    $brand->updated_at ?? now(),
                    'weekly',
                    '0.6'
                );
            });

        BlogPost::query()
            ->where('is_published', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'published_at'])
            ->each(function (BlogPost $post) use (&$urls): void {
                $urls[] = $this->entry(
                    route('blog.show', $post->slug),
                    $post->updated_at ?? $post->published_at ?? now(),
                    'monthly',
                    '0.6'
                );
            });

        Page::query()
            ->where('is_published', true)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('slug')
            ->get(['slug', 'updated_at', 'content'])
            ->each(function (Page $page) use (&$urls): void {
                if (trim(strip_tags((string) $page->content)) === '') {
                    return;
                }
                $urls[] = $this->entry(
                    url('/'.$page->slug),
                    $page->updated_at ?? now(),
                    'monthly',
                    '0.5'
                );
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode('', $urls)
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    protected function entry(string $loc, Carbon|\DateTimeInterface|string $lastmod, string $changefreq, string $priority): string
    {
        $lastmodValue = Carbon::parse($lastmod)->toAtomString();

        return '  <url>'
            .'<loc>'.e($loc).'</loc>'
            .'<lastmod>'.e($lastmodValue).'</lastmod>'
            .'<changefreq>'.e($changefreq).'</changefreq>'
            .'<priority>'.e($priority).'</priority>'
            .'</url>'."\n";
    }
}
