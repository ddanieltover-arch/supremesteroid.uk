<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\Audit\AuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if ($record->wasChanged(['price_amount', 'compare_at_price_amount'])) {
            app(AuditLogger::class)->log(
                eventType: 'price_change',
                auditable: $record,
                oldValues: [
                    'price_amount' => $record->getOriginal('price_amount'),
                    'compare_at_price_amount' => $record->getOriginal('compare_at_price_amount'),
                ],
                newValues: [
                    'price_amount' => $record->price_amount,
                    'compare_at_price_amount' => $record->compare_at_price_amount,
                ],
                reason: 'Catalogue price updated in admin.'
            );
        }
    }
}
