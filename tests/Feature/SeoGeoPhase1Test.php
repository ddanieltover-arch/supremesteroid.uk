<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\HomepageFaq;
use App\Models\ProductImage;
use App\Services\Catalogue\StructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class SeoGeoPhase1Test extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_sitemap_lists_indexable_urls_and_excludes_private_paths(): void
    {
        $product = $this->makeProduct(['name' => 'Sitemap Product', 'sku' => 'SEO-MAP-1', 'slug' => 'sitemap-product']);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/product_images/'.$product->id.'/primary.jpg',
            'is_primary' => true,
            'display_order' => 0,
        ]);

        $author = $this->makeAdmin('seo-sitemap-author@test.com');
        BlogPost::query()->create([
            'author_id' => $author->id,
            'title' => 'Sitemap Article',
            'slug' => 'sitemap-article',
            'excerpt' => 'Educational overview of catalogue topics.',
            'content' => str_repeat('Educational content. ', 40),
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('/shop', $body);
        $this->assertStringContainsString('/product/sitemap-product', $body);
        $this->assertStringContainsString('/blog/sitemap-article', $body);
        $this->assertStringNotContainsString('/checkout', $body);
        $this->assertStringNotContainsString('/cart', $body);
        $this->assertStringNotContainsString('/account', $body);
    }

    public function test_robots_txt_disallows_private_paths_and_points_at_sitemap(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertOk();

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('Disallow: /checkout', $body);
        $this->assertStringContainsString('Disallow: /cart', $body);
        $this->assertStringContainsString('Disallow: /account', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /internal', $body);
        $this->assertStringContainsString('User-agent: GPTBot', $body);
        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }

    public function test_catalogue_path_permanently_redirects_to_shop(): void
    {
        $this->get('/catalogue')
            ->assertRedirect('/shop')
            ->assertStatus(301);
    }

    public function test_product_schema_includes_offer_enrichment_without_fake_ratings(): void
    {
        $product = $this->makeProduct(['name' => 'Schema Product', 'sku' => 'SEO-SCHEMA-1', 'slug' => 'schema-product']);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/product_images/'.$product->id.'/primary.jpg',
            'is_primary' => true,
            'display_order' => 0,
        ]);

        $schema = app(StructuredDataService::class)->forProduct($product->fresh(['brand', 'images']));

        $this->assertSame('Product', $schema['@type']);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertSame('https://schema.org/NewCondition', $schema['offers']['itemCondition']);
        $this->assertArrayHasKey('priceValidUntil', $schema['offers']);
        $this->assertSame('Organization', $schema['offers']['seller']['@type']);
    }

    public function test_homepage_includes_faq_page_schema_when_faqs_exist(): void
    {
        HomepageFaq::query()->create([
            'question' => 'Do you ship within the UK?',
            'answer' => 'Yes. Eligible UK orders use the published shipping options on the storefront.',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('structured_data')
                ->where('structured_data.0.@type', 'Organization')
                ->where('structured_data.1.@type', 'WebSite')
                ->where('structured_data.2.@type', 'FAQPage')
            );
    }

    public function test_blog_show_includes_blog_posting_structured_data(): void
    {
        $author = $this->makeAdmin('seo-blog-author@test.com');
        BlogPost::query()->create([
            'author_id' => $author->id,
            'title' => 'GEO Blog Guide',
            'slug' => 'geo-blog-guide',
            'excerpt' => 'A short educational summary for catalogue readers.',
            'content' => str_repeat('Guide paragraph content. ', 50),
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);

        $this->get('/blog/geo-blog-guide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->has('structured_data', 2)
                ->where('structured_data.0.@type', 'BreadcrumbList')
                ->where('structured_data.1.@type', 'BlogPosting')
                ->where('structured_data.1.headline', 'GEO Blog Guide')
            );
    }

    public function test_llms_txt_is_publicly_available(): void
    {
        $this->assertFileExists(public_path('llms.txt'));
        $contents = (string) file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('Supreme Steroids', $contents);
        $this->assertStringContainsString('/shop', $contents);
        $this->assertStringContainsString('/sitemap.xml', $contents);
    }
}
