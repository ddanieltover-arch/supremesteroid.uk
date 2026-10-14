<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogueImportRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'import_batch_id',
        'import_batch_uuid',
        'source_batch',
        'source_name',
        'source_url',
        'source_product_reference',
        'source_category',
        'source_brand',
        'original_payload_reference',
        'source_payload',
        'normalized_payload',
        'match_status',
        'workflow_status',
        'product_id',
        'matched_product_id',
        'imported_at',
        'issues',
        'review_checklist',
        'classification_status',
        'classification_notes',
        'classification_reviewed_by',
        'classification_reviewed_at',
        'source_price_amount',
        'source_compare_at_price_amount',
        'source_currency',
        'requires_compliance_review',
        'target_category_id',
        'target_brand_id',
    ];

    protected function casts(): array
    {
        return [
            'normalized_payload' => 'array',
            'source_payload' => 'array',
            'issues' => 'array',
            'review_checklist' => 'array',
            'match_status' => ImportMatchStatus::class,
            'workflow_status' => ImportWorkflowStatus::class,
            'imported_at' => 'datetime',
            'classification_reviewed_at' => 'datetime',
            'source_price_amount' => 'decimal:2',
            'source_compare_at_price_amount' => 'decimal:2',
            'requires_compliance_review' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CatalogueImportBatch::class, 'import_batch_uuid');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CatalogueImportImage::class);
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    public function targetBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'target_brand_id');
    }

    public function classificationReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classification_reviewed_by');
    }

    public function displayName(): string
    {
        return (string) ($this->normalized_payload['name'] ?? $this->source_product_reference ?? 'Untitled source record');
    }

    public function sourceSku(): ?string
    {
        $sku = trim((string) ($this->normalized_payload['sku'] ?? ''));

        return $sku !== '' ? $sku : null;
    }

    public function hasSourceSku(): bool
    {
        return $this->sourceSku() !== null;
    }
}
