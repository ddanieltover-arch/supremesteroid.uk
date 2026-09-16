<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Exceptions\InvalidPriceException;
use App\Exceptions\ProductComplianceViolationException;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Compliance\ProductComplianceService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Pricing\PricingService;

class ProductPurchaseEligibilityService
{
    public function __construct(
        protected ProductComplianceService $complianceService,
        protected PricingService $pricingService,
        protected InventoryReservationService $reservations
    ) {}

    /**
     * Determine if a product is eligible for public display and purchase.
     *
     * @return array{is_eligible: bool, reasons: list<string>}
     */
    public function checkEligibility(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        bool $checkInventory = true
    ): array {
        $reasons = [];

        if ($product->status !== ProductStatus::ACTIVE) {
            $reasons[] = "Product status '{$product->status->value}' is not active for retail purchase.";
        }

        if ($product->trashed()) {
            $reasons[] = 'Product has been decommissioned or deleted.';
        }

        if (empty(trim((string) $product->name))) {
            $reasons[] = 'Product title is missing.';
        }
        if (empty(trim((string) $product->sku))) {
            $reasons[] = 'Product SKU identifier is missing.';
        }
        if (empty(trim((string) $product->slug))) {
            $reasons[] = 'Product slug identifier is missing.';
        }

        try {
            $this->pricingService->calculateProductPrice($product, $variant);
        } catch (InvalidPriceException $e) {
            $reasons[] = $e->getMessage();
        }

        if (! in_array($product->classification, ProductClassification::approvedForRetail(), true)) {
            $reasons[] = 'Product classification is not approved for retail distribution.';
        }

        if ($product->compliance_verified_at === null) {
            $reasons[] = 'Product has not completed mandatory compliance verification.';
        }

        if ($product->classification === ProductClassification::RESEARCH_USE) {
            if (empty(trim((string) $product->batch_number))) {
                $reasons[] = 'Research-use items strictly require an authoritative traceable batch number.';
            }
            if (empty(trim((string) $product->lab_verification_reference))) {
                $reasons[] = 'Research-use items strictly require third-party lab verification documentation.';
            }
        }

        try {
            $this->complianceService->validateProductCompliance($product);
        } catch (ProductComplianceViolationException $e) {
            $reasons[] = $e->getMessage();
        }

        if ($variant !== null) {
            if ($variant->product_id !== $product->id) {
                $reasons[] = 'Specified product variant does not belong to this product.';
            }
            if (! $variant->is_active) {
                $reasons[] = 'Selected product variant is inactive.';
            }
        }

        if ($checkInventory && $quantity > 0) {
            $this->reservations->releaseExpiredReservations();
            $product->unsetRelation('inventory');
            $variant?->unsetRelation('inventory');
            $inventory = $variant ? $variant->inventory : $product->inventory;
            if ($inventory) {
                $available = $inventory->available();
                if ($available < $quantity) {
                    $reasons[] = "Insufficient stock available ({$available} in stock, {$quantity} requested).";
                }
            }
        }

        return [
            'is_eligible' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    public function isEligible(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        bool $checkInventory = true
    ): bool {
        return $this->checkEligibility($product, $variant, $quantity, $checkInventory)['is_eligible'];
    }

    /**
     * @throws ProductNotPurchasableException
     */
    public function assertPurchasable(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        bool $checkInventory = true
    ): void {
        $result = $this->checkEligibility($product, $variant, $quantity, $checkInventory);

        if (! $result['is_eligible']) {
            $reasonSummary = implode('; ', $result['reasons']);
            throw new ProductNotPurchasableException(
                "Product '{$product->name}' ({$product->sku}) is not eligible for purchase: {$reasonSummary}",
                $result['reasons']
            );
        }
    }
}
