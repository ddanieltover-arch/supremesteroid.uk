<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Exceptions\ProductComplianceViolationException;
use App\Models\Product;
use App\Services\Compliance\ProductComplianceService;
use PHPUnit\Framework\TestCase;

class ProductComplianceTest extends TestCase
{
    public function test_product_with_prohibited_terms_fails_compliance_validation(): void
    {
        $service = new ProductComplianceService();

        $product = new Product([
            'name' => 'Dangerous Compound',
            'description' => 'Recommended beginner steroid cycle with rapid dosage instructions',
            'classification' => ProductClassification::OTHER_LAWFUL_PRODUCT,
        ]);

        $this->expectException(ProductComplianceViolationException::class);
        $service->validateProductCompliance($product);
    }

    public function test_research_product_without_batch_number_fails_compliance(): void
    {
        $service = new ProductComplianceService();

        $product = new Product([
            'name' => 'Lyophilized Fragment',
            'description' => 'Analytical laboratory standard for research only.',
            'classification' => ProductClassification::RESEARCH_USE,
            'batch_number' => null,
        ]);

        $this->expectException(ProductComplianceViolationException::class);
        $service->validateProductCompliance($product);
    }

    public function test_lawful_product_passes_compliance(): void
    {
        $service = new ProductComplianceService();

        $product = new Product([
            'name' => 'Premium Magnesium Bisglycinate Complex',
            'description' => 'Essential recovery electrolyte formulation.',
            'classification' => ProductClassification::OTC_CONSUMER,
            'batch_number' => 'BATCH-2026-MG',
        ]);

        $service->validateProductCompliance($product);
        $this->assertTrue(true);
    }
}
