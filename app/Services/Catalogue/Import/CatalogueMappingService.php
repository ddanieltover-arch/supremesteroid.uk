<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Enums\ImportMappingStatus;
use App\Models\Brand;
use App\Models\CatalogueBrandMapping;
use App\Models\CatalogueCategoryMapping;
use App\Models\Category;
use Illuminate\Support\Str;

class CatalogueMappingService
{
    public function normalizeLabel(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    public function resolveCategoryMapping(string $sourceCategory, bool $persist = true): CatalogueCategoryMapping
    {
        $sourceCategory = trim($sourceCategory);
        $normalized = $this->normalizeLabel($sourceCategory);

        $existing = CatalogueCategoryMapping::query()
            ->where('normalized_source_category', $normalized)
            ->first();

        if ($existing) {
            return $existing;
        }

        $target = Category::query()
            ->get()
            ->first(fn (Category $category) => $this->normalizeLabel($category->name) === $normalized);

        $mapping = new CatalogueCategoryMapping([
            'source_category' => $sourceCategory,
            'normalized_source_category' => $normalized,
            'target_category_id' => $target?->id,
            'status' => $target ? ImportMappingStatus::APPROVED : ImportMappingStatus::PENDING,
            'approved_at' => $target ? now() : null,
        ]);

        if ($persist && $normalized !== '') {
            $mapping->save();
        }

        return $mapping;
    }

    public function resolveBrandMapping(string $sourceBrand, bool $persist = true): CatalogueBrandMapping
    {
        $sourceBrand = trim($sourceBrand);
        $normalized = $this->normalizeLabel($sourceBrand);

        $existing = CatalogueBrandMapping::query()
            ->where('normalized_source_brand', $normalized)
            ->first();

        if ($existing) {
            return $existing;
        }

        $exact = Brand::query()
            ->get()
            ->first(fn (Brand $brand) => $this->normalizeLabel($brand->name) === $normalized);

        $possible = null;
        if (! $exact && $normalized !== '') {
            $possible = Brand::query()
                ->get()
                ->first(function (Brand $brand) use ($normalized) {
                    similar_text($this->normalizeLabel($brand->name), $normalized, $percent);

                    return $percent >= 86;
                });
        }

        $mapping = new CatalogueBrandMapping([
            'source_brand' => $sourceBrand,
            'normalized_source_brand' => $normalized,
            'target_brand_id' => $exact?->id,
            'status' => $exact ? ImportMappingStatus::APPROVED : ImportMappingStatus::PENDING,
            'match_hint' => $exact ? 'exact' : ($possible ? 'possible:'.$possible->name : null),
            'approved_at' => $exact ? now() : null,
        ]);

        if ($persist && $normalized !== '') {
            $mapping->save();
        }

        return $mapping;
    }

    public function ensureHiddenCategory(string $name): ?Category
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return Category::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_visible' => false]
        );
    }

    public function ensureBrand(string $name): ?Brand
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return Brand::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true]
        );
    }

    public function applyCategoryMapping(\App\Models\CatalogueImportRecord $record, string $categoryId, ?string $actorId = null): CatalogueCategoryMapping
    {
        $targetCategory = Category::find($categoryId);
        if (! $targetCategory) {
            throw new \InvalidArgumentException("Target category ID [{$categoryId}] does not exist.");
        }

        $record->target_category_id = $targetCategory->id;
        if ($record->product) {
            $record->product->update(['category_id' => $targetCategory->id]);
        }
        $record->save();

        $normalized = $this->normalizeLabel((string) $record->source_category);
        $mapping = CatalogueCategoryMapping::query()
            ->where('normalized_source_category', $normalized)
            ->first();

        if ($mapping) {
            $mapping->target_category_id = $targetCategory->id;
            $mapping->status = ImportMappingStatus::APPROVED;
            $mapping->approved_by = $actorId;
            $mapping->approved_at = now();
            $mapping->save();
        } elseif (filled($record->source_category)) {
            $mapping = CatalogueCategoryMapping::query()->create([
                'source_category' => $record->source_category,
                'normalized_source_category' => $normalized,
                'target_category_id' => $targetCategory->id,
                'status' => ImportMappingStatus::APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);
        }

        app(\App\Services\Audit\AuditLogger::class)->log(
            eventType: 'catalogue_import.category_mapped',
            auditable: $record,
            newValues: ['target_category_id' => $targetCategory->id],
            reason: 'Assigned target category to staged product record.'
        );

        return $mapping ?? new CatalogueCategoryMapping(['target_category_id' => $targetCategory->id]);
    }

    public function applyBrandMapping(\App\Models\CatalogueImportRecord $record, string $brandId, ?string $actorId = null): CatalogueBrandMapping
    {
        $targetBrand = Brand::find($brandId);
        if (! $targetBrand) {
            throw new \InvalidArgumentException("Target brand ID [{$brandId}] does not exist.");
        }

        $record->target_brand_id = $targetBrand->id;
        if ($record->product) {
            $record->product->update(['brand_id' => $targetBrand->id]);
        }
        $record->save();

        $normalized = $this->normalizeLabel((string) $record->source_brand);
        $mapping = CatalogueBrandMapping::query()
            ->where('normalized_source_brand', $normalized)
            ->first();

        if ($mapping) {
            $mapping->target_brand_id = $targetBrand->id;
            $mapping->status = ImportMappingStatus::APPROVED;
            $mapping->approved_by = $actorId;
            $mapping->approved_at = now();
            $mapping->save();
        } elseif (filled($record->source_brand)) {
            $mapping = CatalogueBrandMapping::query()->create([
                'source_brand' => $record->source_brand,
                'normalized_source_brand' => $normalized,
                'target_brand_id' => $targetBrand->id,
                'status' => ImportMappingStatus::APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);
        }

        app(\App\Services\Audit\AuditLogger::class)->log(
            eventType: 'catalogue_import.brand_mapped',
            auditable: $record,
            newValues: ['target_brand_id' => $targetBrand->id],
            reason: 'Assigned target brand to staged product record.'
        );

        return $mapping ?? new CatalogueBrandMapping(['target_brand_id' => $targetBrand->id]);
    }
}
