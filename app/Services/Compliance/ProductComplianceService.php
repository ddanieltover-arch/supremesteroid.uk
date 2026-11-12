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
        $textToCheck = strtolower($this->complianceTextCorpus($product));

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

    /**
     * Collect name, description, and guidance/content fields for compliance scanning.
     */
    protected function complianceTextCorpus(Product $product): string
    {
        $parts = [
            $product->name ?? '',
            $product->description ?? '',
            $product->short_description ?? '',
            $product->safety_guidelines ?? '',
        ];

        $content = null;
        if ($product->relationLoaded('content')) {
            $content = $product->getRelation('content');
        } elseif ($product->exists) {
            $content = $product->content()->first();
        }

        if ($content) {
            $parts = array_merge($parts, [
                $content->overview ?? '',
                $content->important_information ?? '',
                $content->why_choose ?? '',
                $content->how_supplied ?? '',
                $content->where_it_fits ?? '',
                $content->who_its_for ?? '',
                $content->what_you_get ?? '',
                $content->answer_capsule ?? '',
                $content->documentation ?? '',
                $content->packaging_information ?? '',
                $content->manufacturer_information ?? '',
                $content->shipping_information ?? '',
                $content->storage_information ?? '',
                $content->composition ?? '',
            ]);

            foreach ((array) ($content->highlights ?? []) as $highlight) {
                if (is_string($highlight)) {
                    $parts[] = $highlight;
                }
            }

            foreach ((array) ($content->key_differences ?? []) as $difference) {
                if (is_string($difference)) {
                    $parts[] = $difference;
                }
            }

            foreach ((array) ($content->faqs ?? []) as $faq) {
                if (! is_array($faq)) {
                    continue;
                }
                $parts[] = (string) ($faq['question'] ?? '');
                $parts[] = (string) ($faq['answer'] ?? '');
            }
        }

        return implode(' ', $parts);
    }
}
