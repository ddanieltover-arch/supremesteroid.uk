<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ImportImageStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportMappingStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Category;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\Import\CatalogueMappingService;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogueImportService
{
    public function __construct(
        protected OriginalProductCopyService $copy,
        protected VariantCatalogService $variants,
        protected RestrictedProductDetector $restrictedDetector,
        protected CatalogueMappingService $mappings,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Stage a source record. Never marks the product ACTIVE or purchasable.
     *
     * @param  array<string, mixed>  $source
     * @param  array{dry_run?: bool, batch_uuid?: ?string, create_product?: bool}  $options
     */
    public function stage(array $source, string $batchId, string $sourceName = 'manual', array $options = []): CatalogueImportRecord
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $createProduct = (bool) ($options['create_product'] ?? true);
        $batchUuid = $options['batch_uuid'] ?? null;

        $normalized = $this->normalize($source, $sourceName);
        $match = $this->detectMatch($normalized);
        $restriction = $this->restrictedDetector->evaluate($normalized);
        $issues = $this->collectIssues($normalized, $match, $restriction);
        $workflow = $this->resolveWorkflow($match['status'], $restriction['requires_review'], $issues);

        $attributes = [
            'import_batch_id' => $batchId,
            'import_batch_uuid' => $dryRun ? null : $batchUuid,
            'source_batch' => $batchId,
            'source_name' => $sourceName,
            'source_url' => $source['source_url'] ?? $source['url'] ?? null,
            'source_product_reference' => $normalized['source_product_reference'],
            'source_category' => $normalized['source_category'],
            'source_brand' => $normalized['source_brand'],
            'original_payload_reference' => $normalized['original_payload_reference'],
            'source_payload' => $this->sanitizedSourcePayload($source),
            'normalized_payload' => $normalized,
            'match_status' => $match['status'],
            'workflow_status' => $workflow,
            'matched_product_id' => $match['product']?->id,
            'imported_at' => now(),
            'issues' => $issues,
            'review_checklist' => $this->emptyChecklist(),
            'classification_status' => $restriction['requires_review']
                ? null
                : ($normalized['classification'] ?? ProductClassification::OTHER_LAWFUL_PRODUCT->value),
            'classification_notes' => $restriction['requires_review']
                ? implode(' ', $restriction['reasons'])
                : null,
            'source_price_amount' => is_numeric($normalized['price_amount']) ? $normalized['price_amount'] : null,
            'source_compare_at_price_amount' => is_numeric($normalized['compare_at_price_amount'] ?? null)
                ? $normalized['compare_at_price_amount']
                : null,
            'source_currency' => $normalized['currency'] ?? 'GBP',
            'requires_compliance_review' => $restriction['requires_review'],
        ];

        if ($dryRun) {
            $record = new CatalogueImportRecord($attributes);
            $record->id = (string) Str::uuid();
            $record->exists = false;

            return $record;
        }

        return DB::transaction(function () use ($attributes, $match, $normalized, $createProduct, $restriction, $source) {
            if (filled($normalized['source_category'] ?? null)) {
                $categoryMapping = $this->mappings->resolveCategoryMapping((string) $normalized['source_category']);
                $attributes['target_category_id'] = $categoryMapping->status === ImportMappingStatus::APPROVED
                    ? $categoryMapping->target_category_id
                    : null;
            }

            if (filled($normalized['source_brand'] ?? null)) {
                $brandMapping = $this->mappings->resolveBrandMapping((string) $normalized['source_brand']);
                $attributes['target_brand_id'] = $brandMapping->status === ImportMappingStatus::APPROVED
                    ? $brandMapping->target_brand_id
                    : null;
            }

            $record = CatalogueImportRecord::query()->create($attributes);

            $this->stageImages($record, $source);

            if ($match['status'] === ImportMatchStatus::EXACT_MATCH) {
                $record->workflow_status = ImportWorkflowStatus::DUPLICATE;
                $record->save();

                $this->auditLogger->log(
                    eventType: 'catalogue_import.duplicate',
                    auditable: $record,
                    newValues: ['match_status' => ImportMatchStatus::EXACT_MATCH->value],
                    reason: 'Exact SKU match — no product created.'
                );

                return $record->fresh(['images']);
            }

            if ($createProduct && $match['status'] !== ImportMatchStatus::EXACT_MATCH) {
                $product = $this->createDraft($normalized, $match['status'], $restriction['requires_review'], $record);
                $record->product_id = $product->id;
                $record->save();
                $this->bindStagedImagesToVariants($record, $product);
            }

            $this->auditLogger->log(
                eventType: 'catalogue_import.staged',
                auditable: $record,
                newValues: [
                    'workflow_status' => $record->workflow_status?->value,
                    'match_status' => $record->match_status?->value,
                    'requires_compliance_review' => $record->requires_compliance_review,
                ],
                reason: 'Catalogue source record staged for review.'
            );

            return $record->fresh(['product', 'images']);
        });
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function normalize(array $source, string $sourceName = 'manual'): array
    {
        $name = trim((string) ($source['name'] ?? $source['title'] ?? ''));
        $sku = strtoupper(trim((string) ($source['sku'] ?? '')));
        $brand = trim((string) ($source['brand'] ?? $source['source_brand'] ?? ''));
        $category = trim((string) ($source['category'] ?? $source['source_category'] ?? ''));
        $subcategory = trim((string) ($source['subcategory'] ?? $source['source_subcategory'] ?? ''));

        $price = $source['price_amount'] ?? $source['price'] ?? null;
        $compare = $source['compare_at_price_amount'] ?? $source['compare_at_price'] ?? null;

        $variants = [];
        foreach ((array) ($source['variants'] ?? []) as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variants[] = [
                'sku' => isset($variant['sku']) ? strtoupper(trim((string) $variant['sku'])) : null,
                'name' => trim((string) ($variant['name'] ?? '')),
                'price_amount' => $variant['price_amount'] ?? $variant['price'] ?? null,
                'compare_at_price_amount' => $variant['compare_at_price_amount'] ?? $variant['compare_at_price'] ?? null,
                'attributes' => $this->filterCommercialAttributes((array) ($variant['attributes'] ?? [])),
            ];
        }

        return [
            'name' => $name,
            'normalized_name' => $this->normalizeName($name),
            'sku' => $sku !== '' ? $sku : null,
            'brand' => $brand,
            'category' => $category,
            'subcategory' => $subcategory,
            'source_brand' => $brand,
            'source_category' => $category,
            'source_product_reference' => (string) ($source['source_product_reference'] ?? $source['reference'] ?? ($sku !== '' ? $sku : Str::slug($name))),
            'price_amount' => is_numeric($price) ? number_format((float) $price, 2, '.', '') : null,
            'compare_at_price_amount' => is_numeric($compare) ? number_format((float) $compare, 2, '.', '') : null,
            'currency' => strtoupper(trim((string) ($source['currency'] ?? 'GBP'))) ?: 'GBP',
            'format' => $this->nullableTrim($source['format'] ?? null),
            'pack_size' => $this->nullableTrim($source['pack_size'] ?? $source['size'] ?? null),
            'product_type' => $this->nullableTrim($source['product_type'] ?? null),
            'colour' => $this->nullableTrim($source['colour'] ?? $source['color'] ?? null),
            'flavour' => $this->nullableTrim($source['flavour'] ?? $source['flavor'] ?? null),
            'model' => $this->nullableTrim($source['model'] ?? null),
            'availability' => $this->nullableTrim($source['availability'] ?? null),
            'description_source_reference' => $this->nullableTrim($source['description_source_reference'] ?? $source['source_url'] ?? null),
            'image_references' => $this->normalizeImageReferences($source),
            'variants' => $variants,
            'attributes' => $this->filterCommercialAttributes((array) ($source['attributes'] ?? [])),
            'classification' => $source['classification'] ?? null,
            'requires_compliance_review' => (bool) ($source['requires_compliance_review'] ?? false),
            'verification_documents' => is_array($source['verification_documents'] ?? null) ? $source['verification_documents'] : [],
            'original_payload_reference' => $source['original_payload_reference'] ?? sha1($sourceName.'|'.$name.'|'.$sku),
            'naming' => [
                'brand' => $brand !== '' ? $brand : null,
                'product_name' => $name !== '' ? $name : null,
                'product_type' => $this->nullableTrim($source['product_type'] ?? null),
                'variant' => $this->nullableTrim($source['variant_label'] ?? null),
                'pack_size' => $this->nullableTrim($source['pack_size'] ?? $source['size'] ?? null),
                'size' => $this->nullableTrim($source['size'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{status: ImportMatchStatus, product: ?Product}
     */
    public function detectMatch(array $normalized): array
    {
        $candidateSku = $normalized['sku'] ?? null;
        if (filled($candidateSku) && ! str_starts_with(strtoupper((string) $candidateSku), 'IMP-')) {
            $exact = Product::query()->where('sku', $candidateSku)->first();
            if ($exact) {
                return ['status' => ImportMatchStatus::EXACT_MATCH, 'product' => $exact];
            }

            foreach ((array) ($normalized['variants'] ?? []) as $variant) {
                $variantSku = $variant['sku'] ?? null;
                if (! filled($variantSku) || str_starts_with(strtoupper((string) $variantSku), 'IMP-')) {
                    continue;
                }
                $exactVariant = Product::query()->where('sku', $variantSku)->first()
                    ?? Product::query()->whereHas('variants', fn ($q) => $q->where('sku', $variantSku))->first();
                if ($exactVariant) {
                    return ['status' => ImportMatchStatus::EXACT_MATCH, 'product' => $exactVariant];
                }
            }
        }

        if (filled($normalized['source_product_reference'] ?? null)) {
            $existingRecord = CatalogueImportRecord::query()
                ->where('source_product_reference', $normalized['source_product_reference'])
                ->whereNotNull('product_id')
                ->with('product')
                ->first();
            if ($existingRecord?->product) {
                return ['status' => ImportMatchStatus::POSSIBLE_DUPLICATE, 'product' => $existingRecord->product];
            }
        }

        $possible = null;
        if (filled($normalized['normalized_name'] ?? null)) {
            $tokens = array_values(array_filter(explode(' ', (string) $normalized['normalized_name'])));
            $possible = Product::query()
                ->with('brand')
                ->when($tokens !== [], function ($query) use ($tokens) {
                    foreach (array_slice($tokens, 0, 6) as $token) {
                        $query->where('name', 'like', '%'.$token.'%');
                    }
                })
                ->limit(50)
                ->get()
                ->first(function (Product $product) use ($normalized) {
                    if ($this->normalizeName((string) $product->name) !== $normalized['normalized_name']) {
                        return false;
                    }

                    if (filled($normalized['brand'] ?? null) && $product->brand) {
                        return $this->normalizeName($product->brand->name) === $this->normalizeName((string) $normalized['brand']);
                    }

                    return true;
                });
        }

        if ($possible) {
            return ['status' => ImportMatchStatus::POSSIBLE_DUPLICATE, 'product' => $possible];
        }

        return ['status' => ImportMatchStatus::NEW, 'product' => null];
    }

    /**
     * @return list<array{key: string, label: string, passed: bool}>
     */
    public function validationErrors(CatalogueImportRecord $record): array
    {
        $normalized = $record->normalized_payload ?? [];
        $checks = [
            ['key' => 'identity', 'label' => 'Product identity', 'passed' => filled($normalized['name'] ?? null)],
            ['key' => 'brand', 'label' => 'Brand mapping', 'passed' => $record->target_brand_id !== null || filled($normalized['brand'] ?? null)],
            ['key' => 'category', 'label' => 'Category mapping', 'passed' => $record->target_category_id !== null || filled($normalized['category'] ?? null)],
            ['key' => 'price', 'label' => 'Price documented', 'passed' => is_numeric($record->source_price_amount)],
            ['key' => 'variants', 'label' => 'Variant structure', 'passed' => true],
            ['key' => 'media', 'label' => 'Media status', 'passed' => $record->images()->where('status', ImportImageStatus::APPROVED)->exists() || $record->images()->doesntExist()],
            ['key' => 'classification', 'label' => 'Classification status', 'passed' => filled($record->classification_status) && ! $record->requires_compliance_review],
        ];

        return array_values(array_filter($checks, fn (array $check) => ! $check['passed']));
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function createDraft(
        array $normalized,
        ImportMatchStatus $matchStatus,
        bool $requiresComplianceReview,
        CatalogueImportRecord $record
    ): Product {
        $brand = null;
        if ($record->target_brand_id) {
            $brand = Brand::query()->find($record->target_brand_id);
        }
        $brand ??= $this->mappings->ensureBrand((string) ($normalized['brand'] ?: $normalized['source_brand']));

        $category = null;
        if ($record->target_category_id) {
            $category = Category::query()->find($record->target_category_id);
        }
        $category ??= $this->mappings->ensureHiddenCategory((string) ($normalized['category'] ?: $normalized['source_category']));

        $copy = $this->copy->compose($normalized, $brand, $category);
        $sku = (filled($normalized['sku'] ?? null) && ! str_starts_with(strtoupper((string) $normalized['sku']), 'IMP-'))
            ? (string) $normalized['sku']
            : ('IMP-'.strtoupper(Str::random(10)));

        $classification = null;
        if (! $requiresComplianceReview && filled($normalized['classification'] ?? null)) {
            $classification = ProductClassification::tryFrom((string) $normalized['classification']);
        }
        if (! $requiresComplianceReview && $classification === null) {
            $classification = ProductClassification::OTHER_LAWFUL_PRODUCT;
        }

        $product = Product::query()->create([
            'name' => $copy['name'] !== '' ? $copy['name'] : 'Imported product',
            'slug' => $this->uniqueSlug(Str::slug($copy['name'] ?: 'imported-product')),
            'sku' => $sku,
            'brand_id' => $brand?->id,
            'category_id' => $category?->id,
            'price_amount' => is_numeric($normalized['price_amount']) ? $normalized['price_amount'] : 0.01,
            'compare_at_price_amount' => is_numeric($normalized['compare_at_price_amount'] ?? null) ? $normalized['compare_at_price_amount'] : null,
            'currency' => $normalized['currency'] ?? 'GBP',
            'status' => $requiresComplianceReview ? ProductStatus::COMPLIANCE_REVIEW : ProductStatus::IMPORTED,
            'classification' => $classification ?? ProductClassification::OTHER_LAWFUL_PRODUCT,
            'short_description' => $requiresComplianceReview ? null : $copy['short_description'],
            'description' => $requiresComplianceReview ? null : $copy['description'],
            'compliance_verified_at' => null,
            'is_demo' => false,
            'is_featured' => false,
        ]);

        if ($category) {
            $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true]]);
        }

        if (! $requiresComplianceReview) {
            $product->content()->create([
                'overview' => $copy['overview'],
                'highlights' => $copy['highlights'],
                'specifications' => $copy['specifications'],
                'packaging_information' => $copy['packaging_information'],
                'manufacturer_information' => $copy['manufacturer_information'],
                'documentation' => $copy['documentation'],
                'shipping_information' => $copy['shipping_information'],
                'important_information' => $copy['important_information'],
            ]);
        } else {
            $product->content()->create([
                'overview' => null,
                'highlights' => [],
                'specifications' => [],
                'important_information' => 'This record is held for compliance review and must not be sold until lawful status is confirmed.',
            ]);
        }

        foreach ($normalized['attributes'] as $name => $value) {
            if (! is_string($name) || ! is_scalar($value)) {
                continue;
            }
            $product->attributes()->create([
                'attribute_name' => strtoupper((string) $name),
                'attribute_value' => (string) $value,
                'is_public' => true,
            ]);
        }

        foreach ($normalized['variants'] as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $pairs = [];
            foreach ((array) ($variant['attributes'] ?? []) as $attrName => $attrValue) {
                $pairs[(string) $attrName] = (string) $attrValue;
            }

            $this->variants->create($product, [
                'sku' => $variant['sku'] ?? $product->sku.'-V'.($index + 1),
                'name' => $variant['name'] ?? 'Option '.($index + 1),
                'price_amount' => $variant['price_amount'] ?? $product->price_amount,
                'compare_at_price_amount' => $variant['compare_at_price_amount'] ?? null,
                'is_active' => false,
            ], $pairs);
        }

        foreach ((array) ($normalized['verification_documents'] ?? []) as $document) {
            if (! is_array($document)) {
                continue;
            }
            $product->attributes()->create([
                'attribute_name' => 'VERIFICATION_DOCUMENT_REVIEW',
                'attribute_value' => json_encode([
                    'label' => $document['label'] ?? 'Document',
                    'reference' => $document['reference'] ?? null,
                    'status' => 'INTERNAL_REVIEW',
                ]),
                'is_public' => false,
            ]);
        }

        unset($matchStatus);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    protected function stageImages(CatalogueImportRecord $record, array $source): void
    {
        $images = $this->normalizeImageReferences($source);
        foreach ($images as $index => $image) {
            CatalogueImportImage::query()->create([
                'catalogue_import_record_id' => $record->id,
                'source_url' => $image['url'],
                'source_name' => $record->source_name,
                'source_variant_sku' => $image['variant_sku'] ?? null,
                'source_variant_name' => $image['variant_name'] ?? null,
                'normalized_variant_candidate' => $image['variant_candidate'] ?? null,
                'alt_text' => $image['alt'] ?? null,
                'sort_order' => $index,
                'is_primary' => (bool) ($image['is_primary'] ?? $index === 0),
                'status' => ImportImageStatus::PENDING,
            ]);
        }

        // Variant-embedded images (images declared on each variant).
        foreach ((array) ($source['variants'] ?? []) as $variant) {
            if (! is_array($variant)) {
                continue;
            }
            $variantSku = isset($variant['sku']) ? strtoupper(trim((string) $variant['sku'])) : null;
            $variantName = trim((string) ($variant['name'] ?? ''));
            $variantImages = $this->normalizeImageReferences($variant);
            foreach ($variantImages as $index => $image) {
                CatalogueImportImage::query()->create([
                    'catalogue_import_record_id' => $record->id,
                    'source_url' => $image['url'],
                    'source_name' => $record->source_name,
                    'source_variant_sku' => $image['variant_sku'] ?? $variantSku,
                    'source_variant_name' => $image['variant_name'] ?? ($variantName !== '' ? $variantName : null),
                    'normalized_variant_candidate' => $image['variant_candidate'] ?? array_filter([
                        'sku' => $variantSku,
                        'name' => $variantName !== '' ? $variantName : null,
                        'attributes' => $this->filterCommercialAttributes((array) ($variant['attributes'] ?? [])),
                    ]),
                    'alt_text' => $image['alt'] ?? ($variantName !== '' ? $variantName : null),
                    'sort_order' => 100 + $index,
                    'is_primary' => (bool) ($image['is_primary'] ?? $index === 0),
                    'status' => ImportImageStatus::PENDING,
                ]);
            }
        }
    }

    protected function bindStagedImagesToVariants(CatalogueImportRecord $record, Product $product): void
    {
        $product->loadMissing('variants');

        foreach ($record->images()->get() as $image) {
            /** @var CatalogueImportImage $image */
            if ($image->product_variant_id !== null) {
                continue;
            }

            $sku = $image->source_variant_sku
                ? strtoupper((string) $image->source_variant_sku)
                : strtoupper((string) ($image->normalized_variant_candidate['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            $variant = $product->variants->first(fn ($item) => strtoupper((string) $item->sku) === $sku);
            if (! $variant) {
                continue;
            }

            $image->product_variant_id = $variant->id;
            $image->save();
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<array{url: string, alt: ?string, variant_sku: ?string, variant_name: ?string, variant_candidate: ?array, is_primary: ?bool}>
     */
    protected function normalizeImageReferences(array $source): array
    {
        $raw = $source['images'] ?? $source['image_references'] ?? $source['image_urls'] ?? [];
        if (is_string($raw) && $raw !== '') {
            $raw = array_values(array_filter(array_map('trim', explode('|', $raw))));
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = [
                    'url' => trim($item),
                    'alt' => null,
                    'variant_sku' => null,
                    'variant_name' => null,
                    'variant_candidate' => null,
                    'is_primary' => null,
                ];
                continue;
            }
            if (is_array($item) && filled($item['url'] ?? $item['source_url'] ?? null)) {
                $variantSku = isset($item['variant_sku']) ? strtoupper(trim((string) $item['variant_sku'])) : null;
                $variantName = isset($item['variant_name']) ? trim((string) $item['variant_name']) : null;
                $out[] = [
                    'url' => (string) ($item['url'] ?? $item['source_url']),
                    'alt' => isset($item['alt']) ? (string) $item['alt'] : null,
                    'variant_sku' => $variantSku !== '' ? $variantSku : null,
                    'variant_name' => $variantName !== '' ? $variantName : null,
                    'variant_candidate' => isset($item['variant']) && is_array($item['variant'])
                        ? $item['variant']
                        : ($variantSku || $variantName ? array_filter(['sku' => $variantSku, 'name' => $variantName]) : null),
                    'is_primary' => array_key_exists('is_primary', $item) ? (bool) $item['is_primary'] : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    protected function filterCommercialAttributes(array $attributes): array
    {
        $allowed = ['format', 'size', 'pack_size', 'colour', 'color', 'model', 'flavour', 'flavor'];
        $blocked = ['dose', 'dosage', 'cycle', 'stack', 'injection', 'iu', 'mg/ml', 'recommended_use'];
        $clean = [];

        foreach ($attributes as $name => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $key = strtolower(trim((string) $name));
            if (in_array($key, $blocked, true)) {
                continue;
            }
            if ($allowed !== [] && ! in_array($key, $allowed, true) && ! str_starts_with($key, 'commercial_')) {
                // Allow unknown commercial keys that are not blocked medical/dosing keys.
            }
            $clean[(string) $name] = trim((string) $value);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array{requires_review: bool, reasons: list<string>}  $restriction
     * @param  array{status: ImportMatchStatus, product: ?Product}  $match
     * @return list<string>
     */
    protected function collectIssues(array $normalized, array $match, array $restriction): array
    {
        $issues = [];
        if (! filled($normalized['name'] ?? null)) {
            $issues[] = 'Missing product name.';
        }
        if (! is_numeric($normalized['price_amount'] ?? null)) {
            $issues[] = 'Source price not documented or not numeric.';
        }
        if ($match['status'] === ImportMatchStatus::POSSIBLE_DUPLICATE) {
            $issues[] = 'Possible duplicate of existing catalogue product.';
        }
        if ($match['status'] === ImportMatchStatus::EXACT_MATCH) {
            $issues[] = 'Exact SKU match — record will not create a new product.';
        }
        foreach ($restriction['reasons'] as $reason) {
            $issues[] = $reason;
        }

        return $issues;
    }

    /**
     * @param  list<string>  $issues
     */
    protected function resolveWorkflow(ImportMatchStatus $match, bool $requiresCompliance, array $issues): ImportWorkflowStatus
    {
        if ($match === ImportMatchStatus::EXACT_MATCH) {
            return ImportWorkflowStatus::DUPLICATE;
        }

        if ($requiresCompliance) {
            return ImportWorkflowStatus::COMPLIANCE_REVIEW;
        }

        if ($match === ImportMatchStatus::POSSIBLE_DUPLICATE) {
            return ImportWorkflowStatus::REVIEW_REQUIRED;
        }

        if ($issues !== []) {
            return ImportWorkflowStatus::REVIEW_REQUIRED;
        }

        return ImportWorkflowStatus::CONTENT_REVIEW;
    }

    /**
     * @return array<string, bool>
     */
    protected function emptyChecklist(): array
    {
        return [
            'product_identity' => false,
            'brand' => false,
            'category' => false,
            'variant' => false,
            'price' => false,
            'image' => false,
            'description' => false,
            'classification' => false,
            'compliance' => false,
            'seo' => false,
            'inventory' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    protected function sanitizedSourcePayload(array $source): array
    {
        unset($source['description'], $source['long_description'], $source['reviews'], $source['review_text']);

        return $source;
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'imported-product';
        $candidate = $slug;
        $i = 1;
        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    public function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    protected function nullableTrim(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
