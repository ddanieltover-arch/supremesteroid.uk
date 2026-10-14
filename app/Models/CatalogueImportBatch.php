<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogueImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_number',
        'status',
        'source_name',
        'source_type',
        'is_dry_run',
        'source_records',
        'new_count',
        'possible_duplicates',
        'exact_matches',
        'compliance_review',
        'rejected',
        'needs_review',
        'approved_count',
        'rejected_count',
        'review_count',
        'max_records',
        'summary',
        'started_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'is_dry_run' => 'boolean',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(CatalogueImportRecord::class, 'import_batch_uuid');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function reportLines(): array
    {
        return [
            'Catalogue Import #'.$this->batch_number,
            'Status: '.($this->status?->value ?? 'UNKNOWN'),
            'Source records: '.$this->source_records,
            'New: '.$this->new_count,
            'Possible duplicates: '.$this->possible_duplicates,
            'Exact matches: '.$this->exact_matches,
            'Compliance review: '.$this->compliance_review,
            'Rejected: '.$this->rejected_count ?: $this->rejected,
            'Needs review: '.$this->review_count ?: $this->needs_review,
            'Approved: '.$this->approved_count,
            $this->is_dry_run ? 'Mode: dry-run (no catalogue mutations)' : 'Mode: staging write',
        ];
    }
}
