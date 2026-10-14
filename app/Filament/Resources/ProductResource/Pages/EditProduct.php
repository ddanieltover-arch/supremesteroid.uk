<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Filament\Resources\ProductResource;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\CataloguePublicationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getHeading(): string
    {
        $checklist = app(CataloguePublicationService::class)->checklist($this->record);
        $passed = collect($checklist['items'])->where('passed', true)->count();
        $total = count($checklist['items']);

        return $this->record->name.' — publication checklist '.$passed.'/'.$total;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previewStorefront')
                ->label('Storefront preview')
                ->url(fn () => route('admin.catalogue.preview', $this->record->slug))
                ->openUrlInNewTab(),
            Actions\Action::make('publishProduct')
                ->label('Publish product')
                ->color('success')
                ->visible(fn () => $this->record->status !== ProductStatus::ACTIVE)
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(CataloguePublicationService::class)->publish($this->record);
                        Notification::make()->title('Product published')->success()->send();
                        $this->refreshFormData(['status', 'published_at']);
                    } catch (ProductNotPurchasableException $exception) {
                        Notification::make()
                            ->title('Publication checklist failed')
                            ->body(implode(' ', $exception->getReasons() ?: [$exception->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('unpublishProduct')
                ->label('Unpublish')
                ->color('warning')
                ->visible(fn () => $this->record->status === ProductStatus::ACTIVE)
                ->requiresConfirmation()
                ->action(function () {
                    app(CataloguePublicationService::class)->unpublish($this->record);
                    Notification::make()->title('Product unpublished')->success()->send();
                    $this->refreshFormData(['status']);
                }),
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
