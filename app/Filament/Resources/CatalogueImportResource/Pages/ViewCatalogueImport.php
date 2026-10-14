<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogueImportResource\Pages;

use App\Actions\Catalogue\ApproveCatalogueImageAction;
use App\Enums\ImportImageStatus;
use App\Enums\ImportMappingStatus;
use App\Filament\Resources\CatalogueImportResource;
use App\Filament\Resources\ProductResource;
use App\Models\Brand;
use App\Models\CatalogueBrandMapping;
use App\Models\CatalogueCategoryMapping;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\Import\CatalogueMappingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewCatalogueImport extends ViewRecord
{
    protected static string $resource = CatalogueImportResource::class;

    public function getTitle(): string
    {
        return 'UNPUBLISHED preview — '.$this->getRecord()->displayName();
    }

    protected function getHeaderActions(): array
    {
        /** @var CatalogueImportRecord $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('previewStorefront')
                ->label('Storefront preview')
                ->url(fn () => $record->product?->slug
                    ? route('admin.catalogue.preview', $record->product->slug)
                    : null)
                ->openUrlInNewTab()
                ->visible(fn () => filled($record->product?->slug)),
            Actions\Action::make('editProduct')
                ->label('Edit product')
                ->url(fn () => $record->product_id
                    ? ProductResource::getUrl('edit', ['record' => $record->product_id])
                    : null)
                ->visible(fn () => filled($record->product_id)),
            Actions\Action::make('mapCategory')
                ->label('Map category')
                ->icon('heroicon-o-folder')
                ->visible(fn () => ! $record->workflow_status?->isTerminal())
                ->form([
                    Forms\Components\Select::make('category_id')
                        ->label('Target category')
                        ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => $record->product?->category_id ?? $record->target_category_id)
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        app(CatalogueMappingService::class)->applyCategoryMapping(
                            record: $record,
                            categoryId: (string) $data['category_id'],
                            actorId: (string) auth()->id()
                        );
                        Notification::make()->title('Category mapping updated')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Failed to update category mapping')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('mapBrand')
                ->label('Map brand')
                ->icon('heroicon-o-tag')
                ->visible(fn () => ! $record->workflow_status?->isTerminal())
                ->form([
                    Forms\Components\Select::make('brand_id')
                        ->label('Target brand')
                        ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => $record->product?->brand_id ?? $record->target_brand_id)
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        app(CatalogueMappingService::class)->applyBrandMapping(
                            record: $record,
                            brandId: (string) $data['brand_id'],
                            actorId: (string) auth()->id()
                        );
                        Notification::make()->title('Brand mapping updated')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Failed to update brand mapping')->body($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('approvePendingMedia')
                ->label('Approve pending media')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $record->images()->where('status', ImportImageStatus::PENDING->value)->exists())
                ->action(function () use ($record) {
                    $actor = auth()->user();
                    $action = app(ApproveCatalogueImageAction::class);
                    $count = 0;
                    foreach ($record->images()->where('status', ImportImageStatus::PENDING->value)->get() as $image) {
                        /** @var CatalogueImportImage $image */
                        $action->approve($image, $actor);
                        $count++;
                    }
                    Notification::make()->title("Approved {$count} image(s)")->success()->send();
                }),
            Actions\Action::make('retryFailedTransfers')
                ->label('Retry failed transfers')
                ->color('warning')
                ->visible(fn () => $record->images()
                    ->where('status', ImportImageStatus::APPROVED->value)
                    ->whereNotNull('transfer_failure_reason')
                    ->whereNull('copied_at')
                    ->exists())
                ->action(function () use ($record) {
                    $actor = auth()->user();
                    $action = app(ApproveCatalogueImageAction::class);
                    $ok = 0;
                    $failed = 0;
                    $images = $record->images()
                        ->where('status', ImportImageStatus::APPROVED->value)
                        ->whereNull('copied_at')
                        ->get();
                    foreach ($images as $image) {
                        try {
                            $action->fulfill($image, $actor);
                            $ok++;
                        } catch (RuntimeException) {
                            $failed++;
                        }
                    }
                    if ($failed > 0) {
                        Notification::make()
                            ->title('Image transfer failed')
                            ->body("{$ok} copied, {$failed} failed. Use Retry on individual images.")
                            ->danger()
                            ->send();
                    } else {
                        Notification::make()->title("Copied {$ok} image(s)")->success()->send();
                    }
                }),
            Actions\Action::make('rejectStaging')
                ->label('Reject staging record')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reason')->required(),
                ])
                ->action(function (array $data) use ($record) {
                    $record->workflow_status = \App\Enums\ImportWorkflowStatus::REJECTED;
                    $record->classification_notes = trim(($record->classification_notes ? $record->classification_notes.' ' : '').'Rejected: '.$data['reason']);
                    $record->save();
                    Notification::make()->title('Staging record rejected')->success()->send();
                }),
        ];
    }
}
