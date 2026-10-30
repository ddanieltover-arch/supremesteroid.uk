<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Storefront\HomepageService;
use Illuminate\Support\Facades\Cache;

class CataloguePublicationService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected CataloguePublicationChecklistService $checklistService,
        protected SeoService $seoService,
        protected HomepageService $homepageService,
    ) {}

    /**
     * @return array{items: list<array{key: string, label: string, passed: bool, message: string}>, can_publish: bool, status?: string, failures?: list<string>}
     */
    public function checklist(Product $product): array
    {
        $result = $this->checklistService->evaluate($product);

        return [
            'items' => array_map(fn (array $item) => [
                'key' => $item['key'],
                'label' => $item['label'],
                'passed' => $item['passed'],
                'message' => $item['message'],
            ], $result['items']),
            'can_publish' => $result['can_publish'],
            'status' => $result['status'],
            'failures' => $result['failures'],
        ];
    }

    public function publish(Product $product): Product
    {
        if ($product->is_demo) {
            throw new ProductNotPurchasableException(
                'Demo products cannot be published.',
                ['Demo products cannot be published.']
            );
        }

        if ($product->status === ProductStatus::COMPLIANCE_REVIEW) {
            throw new ProductNotPurchasableException(
                'Compliance-held products cannot be published.',
                ['Compliance review incomplete — product is held.']
            );
        }

        $this->seoService->ensureDefaultsForProduct($product->fresh(['brand', 'category', 'images', 'seoMetadata']));
        $product->refresh()->load(['brand', 'category', 'images', 'seoMetadata', 'content', 'variants.inventory', 'inventory']);

        $checklist = $this->checklist($product);

        if (! $checklist['can_publish']) {
            throw new ProductNotPurchasableException(
                'Product cannot be published until the publication checklist passes.',
                $checklist['failures'] ?? collect($checklist['items'])->reject(fn ($item) => $item['passed'])->pluck('message')->all()
            );
        }

        $oldStatus = $product->status?->value;
        $product->status = ProductStatus::ACTIVE;
        $product->published_at = $product->published_at ?? now();
        $product->save();

        CatalogueImportRecord::query()
            ->where('product_id', $product->id)
            ->where('workflow_status', '!=', ImportWorkflowStatus::REJECTED->value)
            ->update(['workflow_status' => ImportWorkflowStatus::PUBLISHED->value]);

        $this->auditLogger->log(
            eventType: 'product_published',
            auditable: $product,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => ProductStatus::ACTIVE->value, 'published_at' => (string) $product->published_at],
            reason: 'Catalogue publication checklist passed.'
        );

        $this->invalidateCatalogueCaches();

        return $product->fresh();
    }

    public function unpublish(Product $product, string $reason = 'Admin unpublished product.'): Product
    {
        $oldStatus = $product->status?->value;
        $product->status = ProductStatus::SUSPENDED;
        $product->save();

        $this->auditLogger->log(
            eventType: 'product_unpublished',
            auditable: $product,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => ProductStatus::SUSPENDED->value],
            reason: $reason
        );

        $this->invalidateCatalogueCaches();

        return $product->fresh();
    }

    public function invalidateCatalogueCaches(): void
    {
        Cache::forget('catalogue.categories.visible');
        Cache::forget('catalogue.categories.visible.v2');
        Cache::forget('catalogue.brands.active');
        Cache::forget('catalogue.brands.active.v2');
        Cache::forget('catalogue.navigation');
        Cache::forget('catalogue.navigation.v4');
        Cache::forget('catalogue.navigation.highlights.v1');
        Cache::forget('catalogue.facets');
        Cache::forget('catalogue.search.facets');
        $this->homepageService->flush();
    }
}
