<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ProductClassification;
use App\Models\Brand;
use App\Models\Category;

class OriginalProductCopyService
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array{
     *   name: string,
     *   short_description: string,
     *   description: string,
     *   highlights: list<string>,
     *   overview: string,
     *   specifications: list<array{name: string, value: string}>,
     *   packaging_information: string,
     *   manufacturer_information: ?string,
     *   documentation: ?string,
     *   shipping_information: string,
     *   important_information: string,
     *   why_choose: string,
     *   how_supplied: string,
     *   where_it_fits: string,
     *   who_its_for: string,
     *   what_you_get: string,
     *   key_differences: list<string>,
     *   answer_capsule: string,
     *   related_reading: list<array{slug: string, title: string}>
     * }
     */
    public function compose(array $normalized, ?Brand $brand = null, ?Category $category = null): array
    {
        $name = $this->normalizedTitle($normalized, $brand);
        $brandName = $brand?->name ?? (string) ($normalized['brand'] ?? 'the listed brand');
        $categoryName = $category?->name ?? (string) ($normalized['category'] ?? 'this category');
        $format = trim((string) ($normalized['format'] ?? $normalized['product_type'] ?? ''));
        $packSize = trim((string) ($normalized['pack_size'] ?? $normalized['size'] ?? ''));
        $flavour = trim((string) ($normalized['flavour'] ?? ''));
        $colour = trim((string) ($normalized['colour'] ?? ''));
        $sku = trim((string) ($normalized['sku'] ?? ''));
        $hasDocs = ! empty($normalized['verification_documents'] ?? [])
            || filled($normalized['lab_verification_reference'] ?? null);
        $classification = $this->resolveClassification($normalized);

        $facts = array_values(array_filter([
            $format !== '' ? 'Specified format: '.$format.'.' : null,
            $packSize !== '' ? 'Pack size: '.$packSize.'.' : null,
            $flavour !== '' ? 'Flavour: '.$flavour.'.' : null,
            $colour !== '' ? 'Colour: '.$colour.'.' : null,
            'Brand: '.$brandName.'.',
            'Category: '.$categoryName.'.',
        ]));

        $short = $name.' from '.$brandName.', listed in '.$categoryName.' with the commercial specification shown on this page.';

        $overview = $name.' is listed in the '.$categoryName.' range from '.$brandName.'. '
            .'This overview summarises verified commercial fields only.';

        $description = implode("\n\n", [
            $overview,
            'This page summarises the commercial specification supplied for the product. Availability, packaging, and documentation are confirmed against the store catalogue rather than third-party marketing copy.',
            $facts !== [] ? implode(' ', $facts) : 'Further specification is shown only where a verified record exists.',
            'No medical, performance, dosing, or treatment claims are made on this page. Review the product documentation and applicable regulations before purchase.',
        ]);

        $highlights = array_values(array_filter([
            $format !== '' ? 'Clearly specified product format' : null,
            $packSize !== '' ? 'Pack size listed on the product record' : null,
            $flavour !== '' ? 'Flavour recorded from the commercial specification' : null,
            $colour !== '' ? 'Colour recorded from the commercial specification' : null,
            'Brand and category assigned from the catalogue',
            'Availability confirmed against current inventory',
            'Original store copy — not republished source marketing',
        ]));

        $specifications = array_values(array_filter([
            $brandName !== '' ? ['name' => 'Brand', 'value' => $brandName] : null,
            $categoryName !== '' ? ['name' => 'Category', 'value' => $categoryName] : null,
            $sku !== '' ? ['name' => 'SKU', 'value' => $sku] : null,
            $format !== '' ? ['name' => 'Format', 'value' => $format] : null,
            $packSize !== '' ? ['name' => 'Pack size', 'value' => $packSize] : null,
            $flavour !== '' ? ['name' => 'Flavour', 'value' => $flavour] : null,
            $colour !== '' ? ['name' => 'Colour', 'value' => $colour] : null,
        ]));

        $packaging = $packSize !== ''
            ? 'Packaging is listed as '.$packSize.' according to the catalogue record.'
            : 'Packaging details are shown only where a verified commercial record exists.';

        $manufacturer = $brandName !== '' && $brandName !== 'the listed brand'
            ? 'Manufacturer / brand of record: '.$brandName.'.'
            : null;

        $documentation = $hasDocs
            ? 'Documentation references are held for internal review and are only shown publicly when verified.'
            : null;

        $shipping = 'Shipping options and estimated delivery times are calculated at checkout from the current shipping configuration.';

        $important = 'Important information: this listing uses original store copy based on verified commercial fields. '
            .'It does not include dosing, cycle, stacking, or medical advice. '
            .'Imported records remain under review and are not available for purchase until approved.';

        $whyChoose = $name.' is listed from '.$brandName.' in the '.$categoryName.' range with a verified commercial specification on this page'
            .($format !== '' ? ', including a clearly recorded '.$format.' format' : '')
            .'. Selection is based on catalogue identity, brand packaging, and published store fields — not third-party marketing claims.';

        $howSupplied = 'This product is supplied as a catalogue listing with commercial fields confirmed against the store record'
            .($packSize !== '' ? ' (pack size: '.$packSize.')' : '')
            .'. '
            .($hasDocs
                ? 'Where documentation or lab references exist, they are shown only after verification.'
                : 'Public documentation appears only when a verified reference is attached to the record.')
            .' Fulfilment options are confirmed at UK checkout.';

        $whereItFits = $name.' sits in the '.$categoryName.' category under '.$brandName
            .' on the Supreme Steroids UK catalogue. Shipping and free-delivery eligibility follow the live basket and checkout configuration for UK orders.';

        $whoItsFor = match ($classification) {
            ProductClassification::RESEARCH_USE => 'Intended for customers browsing research-use catalogue listings who need a clear commercial specification, brand identity, and traceable batch information where published. Not medical advice.',
            ProductClassification::OTC_CONSUMER => 'Intended for customers comparing lawful sports-nutrition and consumer catalogue products who want transparent brand, pack, and category information before purchase.',
            default => 'Intended for catalogue shoppers comparing lawful health and wellness listings who need clear brand, category, and packaging details without dosing or treatment guidance.',
        };

        $whatParts = array_values(array_filter([
            $format !== '' ? 'format: '.$format : null,
            $packSize !== '' ? 'pack size: '.$packSize : null,
            $flavour !== '' ? 'flavour: '.$flavour : null,
            $colour !== '' ? 'colour: '.$colour : null,
            'brand: '.$brandName,
            'category: '.$categoryName,
        ]));
        $whatYouGet = 'The listing records '.implode('; ', $whatParts)
            .'. Exact contents of each order follow the selected variant and the packaging details shown on this page.';

        $keyDifferences = array_values(array_filter([
            $format !== '' ? 'Format recorded as '.$format.' on the commercial specification' : null,
            $packSize !== '' ? 'Pack size published as '.$packSize : null,
            $brandName !== '' && $brandName !== 'the listed brand' ? 'Brand of record: '.$brandName : null,
            $hasDocs ? 'Verification documentation referenced when publicly approved' : 'Catalogue copy based on verified commercial fields only',
            'Category placement: '.$categoryName,
        ]));
        $keyDifferences = array_slice($keyDifferences, 0, 4);

        $answerCapsule = $name.' is a '.$categoryName.' catalogue listing from '.$brandName
            .' with commercial specifications shown on this page; it does not include medical or dosing advice.';

        return [
            'name' => $name,
            'short_description' => $short,
            'description' => $description,
            'highlights' => array_slice($highlights, 0, 6),
            'overview' => $overview,
            'specifications' => $specifications,
            'packaging_information' => $packaging,
            'manufacturer_information' => $manufacturer,
            'documentation' => $documentation,
            'shipping_information' => $shipping,
            'important_information' => $important,
            'why_choose' => $whyChoose,
            'how_supplied' => $howSupplied,
            'where_it_fits' => $whereItFits,
            'who_its_for' => $whoItsFor,
            'what_you_get' => $whatYouGet,
            'key_differences' => $keyDifferences,
            'answer_capsule' => $answerCapsule,
            'related_reading' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function normalizedTitle(array $normalized, ?Brand $brand = null): string
    {
        $productName = trim((string) ($normalized['name'] ?? 'Untitled product'));
        $brandName = $brand?->name ?? trim((string) ($normalized['brand'] ?? ''));
        $variant = trim((string) ($normalized['naming']['variant'] ?? $normalized['flavour'] ?? $normalized['colour'] ?? ''));

        // Keep brand separate when already present in the product name.
        if ($brandName !== '' && ! str_contains(mb_strtolower($productName), mb_strtolower($brandName))) {
            $title = $brandName.' '.$productName;
        } else {
            $title = $productName;
        }

        if ($variant !== '' && ! str_contains(mb_strtolower($title), mb_strtolower($variant))) {
            $title .= ' — '.$variant;
        }

        return trim(preg_replace('/\s+/', ' ', $title) ?? $title);
    }

    /**
     * Build a normalized fact bag from an existing product for backfill/compose reuse.
     *
     * @return array<string, mixed>
     */
    public function normalizedFromProduct(\App\Models\Product $product): array
    {
        $attributes = $product->relationLoaded('attributes')
            ? $product->attributes
            : $product->attributes()->get();

        $attrMap = [];
        foreach ($attributes as $attribute) {
            $key = strtolower((string) $attribute->attribute_name);
            $attrMap[$key] = (string) $attribute->attribute_value;
        }

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'format' => $attrMap['format'] ?? $attrMap['product_type'] ?? $attrMap['type'] ?? '',
            'pack_size' => $attrMap['pack_size'] ?? $attrMap['size'] ?? $attrMap['pack size'] ?? '',
            'flavour' => $attrMap['flavour'] ?? $attrMap['flavor'] ?? '',
            'colour' => $attrMap['colour'] ?? $attrMap['color'] ?? '',
            'classification' => $product->classification?->value,
            'lab_verification_reference' => $product->lab_verification_reference,
            'verification_documents' => filled($product->lab_verification_reference) ? ['ref'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function resolveClassification(array $normalized): ProductClassification
    {
        $raw = $normalized['classification'] ?? null;

        if ($raw instanceof ProductClassification) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return ProductClassification::tryFrom($raw) ?? ProductClassification::OTHER_LAWFUL_PRODUCT;
        }

        return ProductClassification::OTHER_LAWFUL_PRODUCT;
    }
}
