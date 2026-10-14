<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

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
     *   important_information: string
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

        $documentation = ! empty($normalized['verification_documents'] ?? [])
            ? 'Documentation references are held for internal review and are only shown publicly when verified.'
            : null;

        $shipping = 'Shipping options and estimated delivery times are calculated at checkout from the current shipping configuration.';

        $important = 'Important information: this listing uses original store copy based on verified commercial fields. '
            .'It does not include dosing, cycle, stacking, or medical advice. '
            .'Imported records remain under review and are not available for purchase until approved.';

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
}
