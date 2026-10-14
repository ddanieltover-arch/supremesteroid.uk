<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\ProductRevision;
use App\Services\Audit\AuditLogger;

class ProductRevisionService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * @param  list<string>  $watched
     */
    public function recordIfChanged(Product $product, array $watched): ?ProductRevision
    {
        $changed = [];
        $old = [];
        $new = [];

        foreach ($watched as $field) {
            if (! $product->wasChanged($field)) {
                continue;
            }

            $changed[] = $field;
            $old[$field] = $this->scalar($product->getOriginal($field));
            $new[$field] = $this->scalar($product->getAttribute($field));
        }

        if ($changed === []) {
            return null;
        }

        $revision = ProductRevision::query()->create([
            'product_id' => $product->id,
            'revision_number' => (int) $product->revisions()->max('revision_number') + 1,
            'actor_id' => auth()->id(),
            'changed_fields' => $changed,
            'old_values' => $old,
            'new_values' => $new,
        ]);

        $this->auditLogger->log(
            eventType: 'product_revision',
            auditable: $product,
            oldValues: $old,
            newValues: $new,
            reason: 'Catalogue field revision recorded.'
        );

        return $revision;
    }

    protected function scalar(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value;
    }
}
