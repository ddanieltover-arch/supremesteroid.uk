<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkIngestRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueBulkIngestRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'run_key',
        'source_type',
        'source_name',
        'status',
        'batch_number',
        'import_batch_uuid',
        'page_size',
        'batch_limit',
        'cursor',
        'stats',
        'last_error',
        'is_dry_run',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkIngestRunStatus::class,
            'cursor' => 'array',
            'stats' => 'array',
            'is_dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(CatalogueImportBatch::class, 'import_batch_uuid');
    }
}
