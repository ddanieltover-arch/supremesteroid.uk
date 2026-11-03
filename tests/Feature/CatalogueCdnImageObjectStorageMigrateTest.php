<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProductImage;
use App\Models\SeoMetadata;
use App\Models\StoredFile;
use App\Services\Catalogue\CatalogueStoredImageCdnFallback;
use App\Services\Catalogue\Import\CatalogueCdnImageObjectStorageMigrateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CatalogueCdnImageObjectStorageMigrateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_dry_run_does_not_mutate_product_images(): void
    {
        $this->fakeCloudDisk();

        $product = $this->makeProduct(['name' => 'Dry Run Cyp', 'sku' => 'DRY-CDN-1', 'slug' => 'dry-run-cyp']);
        $remote = 'https://supremestd.b-cdn.net/products/dry-run.jpg';
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => $remote,
            'alt_text' => null,
            'display_order' => 0,
            'is_primary' => true,
        ]);

        Http::fake([
            'supremestd.b-cdn.net/*' => Http::response($this->fakeImageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $report = $this->service(delayMs: 0)->migrate(execute: false, limit: 10);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['candidates']);
        $this->assertCount(1, $report['migrated']);
        $this->assertSame('dry_run', $report['migrated'][0]['status']);
        $this->assertSame($remote, $image->fresh()->url);
        $this->assertSame(0, StoredFile::query()->count());
        Http::assertNothingSent();
    }

    public function test_execute_uploads_cdn_image_to_object_storage_and_rewrites_url(): void
    {
        $this->fakeCloudDisk();

        $product = $this->makeProduct(['name' => 'Migrate Cyp', 'sku' => 'MIG-CDN-1', 'slug' => 'migrate-cyp']);
        $remote = 'https://supremestd.b-cdn.net/products/migrate-cyp.jpg';
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => $remote,
            'thumbnail_url' => $remote,
            'alt_text' => null,
            'display_order' => 0,
            'is_primary' => true,
        ]);

        SeoMetadata::query()->create([
            'seoable_type' => $product::class,
            'seoable_id' => $product->id,
            'meta_title' => 'Migrate Cyp',
            'og_image_url' => $remote,
        ]);

        Http::fake([
            'supremestd.b-cdn.net/*' => Http::response($this->fakeImageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $report = $this->service(delayMs: 0)->migrate(execute: true, limit: 10);

        $this->assertFalse($report['dry_run']);
        $this->assertCount(1, $report['migrated']);
        $this->assertSame('migrated', $report['migrated'][0]['status']);
        $this->assertSame(1, StoredFile::query()->count());

        $image->refresh();
        $this->assertMatchesRegularExpression('#^product_images/\d{4}/\d{2}/\d{2}/.+\.jpg$#i', (string) $image->url);
        $this->assertSame($image->url, $image->thumbnail_url);
        $this->assertSame('Migrate Cyp', $image->alt_text);

        $publicUrl = $image->publicUrl();
        $this->assertNotNull($publicUrl);
        $this->assertStringNotContainsString('supremestd.b-cdn.net', $publicUrl);
        $this->assertStringContainsString('assets.supremesteroid.uk', $publicUrl);

        $seo = SeoMetadata::query()->where('seoable_id', $product->id)->first();
        $this->assertNotNull($seo);
        $this->assertNotNull($seo->og_image_url);
        $this->assertStringNotContainsString('supremestd.b-cdn.net', (string) $seo->og_image_url);
    }

    public function test_storage_path_candidate_uses_cdn_fallback_map(): void
    {
        $this->fakeCloudDisk();

        $product = $this->makeProduct(['name' => 'Stored Path Cyp', 'sku' => 'STOR-CDN-1', 'slug' => 'stored-path-cyp']);
        $storedPath = '/storage/product_images/'.$product->id.'/primary.jpg';
        $remote = 'https://supremestd.b-cdn.net/products/stored-path.jpg';

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => $storedPath,
            'display_order' => 0,
            'is_primary' => true,
        ]);

        $fallback = \Mockery::mock(CatalogueStoredImageCdnFallback::class);
        $fallback->shouldReceive('remoteUrlForStoredPath')
            ->with($storedPath)
            ->andReturn($remote);
        $this->app->instance(CatalogueStoredImageCdnFallback::class, $fallback);

        Http::fake([
            'supremestd.b-cdn.net/*' => Http::response($this->fakeImageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $report = $this->service(delayMs: 0)->migrate(execute: true, limit: 5);

        $this->assertCount(1, $report['migrated']);
        $this->assertSame(1, StoredFile::query()->count());
        $this->assertMatchesRegularExpression(
            '#^product_images/\d{4}/\d{2}/\d{2}/.+\.jpg$#i',
            (string) ProductImage::query()->where('product_id', $product->id)->value('url')
        );
    }

    public function test_artisan_command_dry_run_writes_audit(): void
    {
        $this->fakeCloudDisk();

        $product = $this->makeProduct(['sku' => 'CMD-CDN-1', 'slug' => 'cmd-cdn-1']);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => 'https://supremestd.b-cdn.net/products/cmd.jpg',
            'is_primary' => true,
            'display_order' => 0,
        ]);

        $this->artisan('catalogue:migrate-cdn-images-to-object-storage', ['--limit' => 5])
            ->assertSuccessful();

        $this->assertFileExists(storage_path('app/catalogue-import/audits/cdn-object-storage-migrate.json'));
        $this->assertSame(
            'https://supremestd.b-cdn.net/products/cmd.jpg',
            ProductImage::query()->where('product_id', $product->id)->value('url')
        );
    }

    protected function service(int $delayMs = 0): CatalogueCdnImageObjectStorageMigrateService
    {
        return new CatalogueCdnImageObjectStorageMigrateService(
            presentation: app(\App\Services\Catalogue\CataloguePresentation::class),
            storage: app(\App\Services\Storage\ObjectStorageService::class),
            cdnFallback: app(CatalogueStoredImageCdnFallback::class),
            delayMs: $delayMs,
        );
    }

    protected function fakeCloudDisk(): void
    {
        Storage::fake('local', [
            'url' => 'https://assets.supremesteroid.uk',
        ]);
        config([
            'filesystems.cloud' => 'local',
            'filesystems.disks.local.url' => 'https://assets.supremesteroid.uk',
        ]);
    }

    protected function fakeImageBytes(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $this->assertNotFalse($png);

        // Localize/migrate reject downloads under 500 bytes.
        return $png.str_repeat('A', 500);
    }
}
