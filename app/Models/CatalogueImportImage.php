<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportImageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueImportImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'catalogue_import_record_id',
        'source_url',
        'source_name',
        'source_variant_sku',
        'source_variant_name',
        'normalized_variant_candidate',
        'product_variant_id',
        'alt_text',
        'sort_order',
        'is_primary',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'product_image_id',
        'copied_at',
        'transfer_failure_reason',
        'transfer_attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportImageStatus::class,
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'approved_at' => 'datetime',
            'copied_at' => 'datetime',
            'transfer_attempts' => 'integer',
            'normalized_variant_candidate' => 'array',
        ];
    }

    public function isCopied(): bool
    {
        return $this->product_image_id !== null && $this->copied_at !== null;
    }

    public function transferFailed(): bool
    {
        return filled($this->transfer_failure_reason) && ! $this->isCopied();
    }

    public function isVariantScoped(): bool
    {
        return filled($this->product_variant_id)
            || filled($this->source_variant_sku)
            || filled($this->normalized_variant_candidate);
    }

    public function safeSourceUrl(): string
    {
        $url = trim((string) $this->source_url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return str_replace(' ', '%20', $url);
        }

        return $url;
    }

    public function importRecord(): BelongsTo
    {
        return $this->belongsTo(CatalogueImportRecord::class, 'catalogue_import_record_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
