<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class SeoGeoPhase2Test extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_blade_html_includes_server_rendered_seo_bootstrap(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('<meta name="robots"', $html);
        $this->assertStringContainsString('property="og:site_name"', $html);
        $this->assertStringContainsString('property="og:locale"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
    }

    public function test_faceted_shop_urls_are_noindex_while_clean_shop_is_indexable(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('seo.robots', 'index,follow')
                ->where('answer_capsule', fn ($value) => is_string($value) && $value !== '')
            );

        $this->get('/shop?sort=price_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.robots', 'noindex,follow')
            );

        $this->get('/shop?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.robots', 'noindex,follow')
            );
    }

    public function test_homepage_exposes_geo_answer_capsule(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('homepage.answer_capsule', fn ($value) => is_string($value) && strlen($value) > 40)
            );
    }

    public function test_category_and_brand_listings_expose_answer_capsules(): void
    {
        $product = $this->makeProduct();
        $category = $product->category;
        $brand = $product->brand;

        $this->get('/category/'.$category->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'category')
                ->where('answer_capsule', fn ($value) => is_string($value) && str_contains($value, $category->name))
            );

        $this->get('/brand/'.$brand->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'brand')
                ->where('answer_capsule', fn ($value) => is_string($value) && str_contains($value, $brand->name))
            );
    }

    public function test_product_page_always_exposes_answer_capsule(): void
    {
        $product = $this->makeProduct(['name' => 'Capsule Fallback Product']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->where('product.answer_capsule', fn ($value) => is_string($value) && str_contains($value, 'Capsule Fallback Product'))
            );
    }

    public function test_lighthouse_ci_stubs_exist(): void
    {
        $this->assertFileExists(base_path('.lighthouserc.cjs'));
        $this->assertFileExists(base_path('.github/workflows/lighthouse.yml'));
    }
}
