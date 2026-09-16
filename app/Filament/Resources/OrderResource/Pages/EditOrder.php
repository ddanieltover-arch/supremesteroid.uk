<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\Audit\AuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('admin_notes')) {
            app(AuditLogger::class)->log(
                eventType: 'manual_order_modification',
                auditable: $this->record,
                oldValues: ['admin_notes' => '[redacted-length]'],
                newValues: ['admin_notes_updated' => true],
                reason: 'Admin notes updated.'
            );
        }
    }
}
