<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Exceptions\ProductComplianceViolationException;
use App\Models\Product;
use App\Models\User;

class ProductComplianceService
{
    /**
     * Prohibited medical claims or terms that violate regulatory boundaries.
     */
    protected array $prohibitedTerms = [
        'steroid cycle',
        'dosage instructions',
        'cure',
        'injectable cycle',
        'bypass doping',
        'evade test',
    ];

    /**
     * Validate and approve a product for lawful sale.
     *
     * @throws ProductComplianceViolationException
     */
    public function approveProduct(Product $product, User $complianceOfficer, ?string $notes = null): Product
    {
        $this->validateProductCompliance($product);

        $product->status = ProductStatus::ACTIVE;
        $product->compliance_verified_at = now();
        $product->compliance_officer_id = $complianceOfficer->id;
        $product->save();

        return $product;
    }

    /**
     * Ensure product complies with regulatory standards.
     *
     * @throws ProductComplianceViolationException
     */
    public function validateProductCompliance(Product $product): void
    {
        $textToCheck = strtolower(($product->name ?? '') . ' ' . ($product->description ?? ''));

        foreach ($this->prohibitedTerms as $prohibited) {
            if (str_contains($textToCheck, $prohibited)) {
                throw new ProductComplianceViolationException(
                    "Product contains prohibited regulatory or medical phrase: '{$prohibited}'."
                );
            }
        }

        if ($product->classification === ProductClassification::RESEARCH_USE && empty($product->batch_number)) {
            throw new ProductComplianceViolationException(
                "Research-use products strictly require a valid traceable batch number before approval."
            );
        }
    }
}
