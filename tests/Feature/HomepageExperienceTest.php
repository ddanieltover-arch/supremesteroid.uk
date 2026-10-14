<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Models\HomepageFaq;
use App\Models\NewsletterSubscriber;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class HomepageExperienceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_homepage_renders_without_authentication_and_exposes_contract(): void
    {
        $featured = $this->makeProduct(['name' => 'Homepage Featured', 'is_featured' => true]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('homepage.hero')
                ->has('homepage.sections')
                ->has('homepage.featuredProducts', 1)
                ->where('homepage.featuredProducts.0.sku', $featured->sku)
                ->where('seo.title', 'Supreme Steroids | Premium Product Collection')
                ->has('structured_data', 2)
                ->where('structured_data.0.@type', 'Organization')
                ->where('structured_data.1.@type', 'WebSite')
                ->has('chrome.announcement')
            );
    }

    public function test_homepage_hides_sale_section_when_no_valid_offers_exist(): void
    {
        $this->makeProduct(['name' => 'No Sale Item']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.saleProducts', [])
            );
    }

    public function test_homepage_includes_sale_products_with_server_prices(): void
    {
        $this->makeProduct([
            'name' => 'Offer Capsules',
            'price_amount' => 40.00,
            'compare_at_price_amount' => 55.00,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('homepage.saleProducts', 1)
                ->where('homepage.saleProducts.0.pricing.is_on_sale', true)
            );
    }

    public function test_inactive_categories_and_brands_are_excluded(): void
    {
        $live = $this->makeProduct(['name' => 'Visible Home Product']);
        $hiddenCategory = $this->makeCategory('Hidden Home Category');
        $hiddenCategory->update(['is_visible' => false]);
        $hiddenBrand = $this->makeBrand('Hidden Home Brand');
        $hiddenBrand->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.categories', fn ($categories) => collect($categories)->contains('id', $live->category_id)
                    && collect($categories)->every(fn ($category) => ($category['id'] ?? null) !== $hiddenCategory->id))
                ->where('homepage.brands', fn ($brands) => collect($brands)->contains('id', $live->brand_id)
                    && collect($brands)->every(fn ($brand) => ($brand['id'] ?? null) !== $hiddenBrand->id))
            );
    }

    public function test_new_arrivals_use_published_eligible_products(): void
    {
        $this->makeProduct([
            'name' => 'Just Published',
            'published_at' => now()->subDay(),
        ]);
        $this->makeProduct([
            'name' => 'Draft Arrival',
            'status' => ProductStatus::DRAFT,
            'sku' => 'DRAFT-HOME-1',
            'slug' => 'draft-home-1',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('homepage.newProducts', 1)
                ->where('homepage.newProducts.0.name', 'Just Published')
            );
    }

    public function test_unpublished_faqs_are_hidden(): void
    {
        HomepageFaq::query()->create([
            'question' => 'Hidden question',
            'answer' => 'Should not appear',
            'is_published' => false,
        ]);
        HomepageFaq::query()->create([
            'question' => 'How is shipping calculated?',
            'answer' => 'Shipping uses the configured store rates at checkout.',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('homepage.faqs', 1)
                ->where('homepage.faqs.0.question', 'How is shipping calculated?')
            );
    }

    public function test_shipping_information_comes_from_configuration(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.shipping.free_shipping_threshold', '300.00')
                ->where('homepage.shipping.methods.0.code', 'UK_STANDARD')
            );
    }

    public function test_newsletter_requires_consent_and_stores_it(): void
    {
        $this->post('/newsletter', [
            'email' => 'reader@example.com',
        ])->assertSessionHasErrors('consent');

        $this->from('/')->post('/newsletter', [
            'email' => 'reader@example.com',
            'consent' => '1',
        ])->assertRedirect('/');

        $subscriber = NewsletterSubscriber::query()->where('email', 'reader@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertNotNull($subscriber->consented_at);
    }

    public function test_policy_placeholder_is_not_indexed(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/Show')
                ->where('page.is_placeholder', true)
                ->where('seo.robots', 'noindex,nofollow')
            );
    }

    public function test_draft_products_do_not_appear_on_the_homepage(): void
    {
        $this->makeProduct([
            'name' => 'Hidden Draft Home',
            'status' => ProductStatus::DRAFT,
            'is_featured' => true,
            'sku' => 'HID-HOME',
            'slug' => 'hidden-draft-home',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.featuredProducts', [])
            );
    }

    public function test_unapproved_reviews_are_not_used_as_social_proof(): void
    {
        $product = $this->makeProduct(['name' => 'Reviewed Home Product']);
        $user = $this->makeCustomer('home-reviewer@customer.test');
        Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
            'title' => 'Pending social proof',
            'body' => 'Should not appear',
            'status' => ReviewStatus::PENDING,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homepage.reviews', [])
            );
    }
}
