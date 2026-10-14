<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Actions\Catalogue\ApproveCatalogueImageAction;
use App\Enums\ImportImageStatus;
use App\Enums\ImportMappingStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Filament\Resources\CatalogueImportResource\Pages;
use App\Models\Brand;
use App\Models\CatalogueBrandMapping;
use App\Models\CatalogueCategoryMapping;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\Import\CatalogueMappingService;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;
use UnitEnum;

class CatalogueImportResource extends Resource
{
    protected static ?string $model = CatalogueImportRecord::class;

    protected static ?string $navigationLabel = 'Catalogue imports';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue Management';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Infolists\Components\TextEntry::make('displayName')->label('Product name')->state(fn (CatalogueImportRecord $record) => $record->displayName()),
            Infolists\Components\TextEntry::make('source_brand')->label('Source brand'),
            Infolists\Components\TextEntry::make('product.brand.name')->label('Mapped brand')->placeholder('Pending review'),
            Infolists\Components\TextEntry::make('source_category')->label('Source category'),
            Infolists\Components\TextEntry::make('product.category.name')->label('Mapped category')->placeholder('Pending review'),
            Infolists\Components\TextEntry::make('source_name')->label('Source'),
            Infolists\Components\TextEntry::make('source_url')->label('Source URL')->placeholder('—'),
            Infolists\Components\TextEntry::make('source_sku')
                ->label('Source SKU')
                ->state(fn (CatalogueImportRecord $record) => $record->sourceSku() ?? 'None published by source')
                ->badge(fn (CatalogueImportRecord $record) => ! $record->hasSourceSku())
                ->color(fn (CatalogueImportRecord $record) => $record->hasSourceSku() ? 'success' : 'gray'),
            Infolists\Components\TextEntry::make('internal_sku')
                ->label('Internal SKU / Identifier')
                ->state(fn (CatalogueImportRecord $record) => $record->product?->isInternalSku()
                    ? $record->product?->sku.' (Internal placeholder)'
                    : ($record->product?->sku ?? '—')),
            Infolists\Components\TextEntry::make('match_status')->badge(),
            Infolists\Components\TextEntry::make('workflow_status')->badge(),
            Infolists\Components\TextEntry::make('classification_status')->label('Classification')->placeholder('Unset — compliance review required'),
            Infolists\Components\TextEntry::make('source_price_amount')->money(fn (CatalogueImportRecord $record) => $record->source_currency ?? 'GBP'),
            Infolists\Components\TextEntry::make('issues')->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (string) $state)->columnSpanFull(),
            Infolists\Components\TextEntry::make('preview_banner')
                ->label('Customer preview')
                ->state('UNPUBLISHED — staging preview only. Source metadata is never shown on the storefront.')
                ->columnSpanFull(),
            Infolists\Components\TextEntry::make('product.name')->label('Staged product title'),
            Infolists\Components\TextEntry::make('product.short_description')->label('Short description')->placeholder('No original copy yet'),
            Infolists\Components\TextEntry::make('product.description')->label('Full description')->placeholder('Held pending compliance/content review')->columnSpanFull(),
            Infolists\Components\RepeatableEntry::make('images')
                ->label('Staged media')
                ->schema([
                    Infolists\Components\ImageEntry::make('preview_image')
                        ->label('Preview')
                        ->state(fn (CatalogueImportImage $image) => $image->safeSourceUrl())
                        ->checkFileExistence(false)
                        ->height(80)
                        ->width(80),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('source_url')->label('Source'),
                    Infolists\Components\TextEntry::make('source_variant_sku')->label('Variant SKU')->placeholder('Product-level'),
                    Infolists\Components\TextEntry::make('product_variant_id')->label('Target variant')->placeholder('—'),
                    Infolists\Components\TextEntry::make('alt_text')->label('Alt'),
                    Infolists\Components\TextEntry::make('transfer_failure_reason')
                        ->label('Transfer')
                        ->placeholder('—')
                        ->color('danger'),
                    Infolists\Components\TextEntry::make('copied_at')->dateTime()->placeholder('Not copied'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('import_batch_id')->label('Batch')->searchable(),
                Tables\Columns\TextColumn::make('source_name')->toggleable(),
                Tables\Columns\TextColumn::make('source_product_reference')->label('Reference')->searchable(),
                Tables\Columns\TextColumn::make('source_sku')
                    ->label('Source SKU')
                    ->state(fn (CatalogueImportRecord $record) => $record->sourceSku() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('normalized_payload.name')->label('Name')->state(fn (CatalogueImportRecord $record) => $record->displayName())->searchable(query: function ($query, string $search) {
                    $query->where('normalized_payload', 'like', '%'.$search.'%');
                }),
                Tables\Columns\TextColumn::make('source_brand')->label('Brand')->toggleable(),
                Tables\Columns\TextColumn::make('source_category')->label('Category')->toggleable(),
                Tables\Columns\TextColumn::make('match_status')->badge(),
                Tables\Columns\TextColumn::make('workflow_status')->badge(),
                Tables\Columns\TextColumn::make('images_count')
                    ->counts('images')
                    ->label('Images'),
                Tables\Columns\IconColumn::make('requires_compliance_review')->boolean()->label('Compliance'),
                Tables\Columns\TextColumn::make('product.name')->label('Staged product')->toggleable(),
                Tables\Columns\TextColumn::make('imported_at')->dateTime()->sortable(),
            ])
            ->defaultSort('imported_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('workflow_status')
                    ->options(collect(ImportWorkflowStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                Tables\Filters\SelectFilter::make('match_status')
                    ->options(collect(ImportMatchStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])),
                Tables\Filters\TernaryFilter::make('requires_compliance_review')->label('Needs compliance'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('Preview'),
                \Filament\Actions\Action::make('openProduct')
                    ->label('Edit product')
                    ->url(fn (CatalogueImportRecord $record) => $record->product_id
                        ? ProductResource::getUrl('edit', ['record' => $record->product_id])
                        : null)
                    ->visible(fn (CatalogueImportRecord $record) => filled($record->product_id)),
                \Filament\Actions\Action::make('approveMedia')
                    ->label('Approve media')
                    ->color('success')
                    ->visible(fn (CatalogueImportRecord $record) => $record->images()
                        ->where('status', ImportImageStatus::PENDING->value)
                        ->exists())
                    ->requiresConfirmation()
                    ->action(function (CatalogueImportRecord $record) {
                        $action = app(ApproveCatalogueImageAction::class);
                        $actor = auth()->user();
                        foreach ($record->images()->where('status', ImportImageStatus::PENDING->value)->get() as $image) {
                            $action->approve($image, $actor);
                        }
                        Notification::make()->title('Pending media approved')->success()->send();
                    }),
                \Filament\Actions\Action::make('retryTransfer')
                    ->label('Retry transfer')
                    ->color('warning')
                    ->visible(fn (CatalogueImportRecord $record) => $record->images()
                        ->where('status', ImportImageStatus::APPROVED->value)
                        ->whereNotNull('transfer_failure_reason')
                        ->whereNull('copied_at')
                        ->exists())
                    ->action(function (CatalogueImportRecord $record) {
                        $action = app(ApproveCatalogueImageAction::class);
                        $actor = auth()->user();
                        $failed = 0;
                        foreach ($record->images()->where('status', ImportImageStatus::APPROVED->value)->whereNull('copied_at')->get() as $image) {
                            try {
                                $action->fulfill($image, $actor);
                            } catch (RuntimeException) {
                                $failed++;
                            }
                        }
                        if ($failed > 0) {
                            Notification::make()->title('Image transfer failed')->danger()->send();
                        } else {
                            Notification::make()->title('Transfers completed')->success()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reject')
                    ->color('danger')
                    ->visible(fn (CatalogueImportRecord $record) => ! $record->workflow_status?->isTerminal())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Rejection reason')->required(),
                    ])
                    ->action(function (CatalogueImportRecord $record, array $data) {
                        $record->workflow_status = ImportWorkflowStatus::REJECTED;
                        $record->classification_notes = trim(($record->classification_notes ? $record->classification_notes.' ' : '').'Rejected: '.$data['reason']);
                        $record->save();
                        app(AuditLogger::class)->log(
                            eventType: 'catalogue_import.rejected',
                            auditable: $record,
                            newValues: ['workflow_status' => ImportWorkflowStatus::REJECTED->value],
                            reason: $data['reason']
                        );
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('approveMediaBulk')
                    ->label('Approve media')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $action = app(ApproveCatalogueImageAction::class);
                        $actor = auth()->user();
                        foreach ($records as $record) {
                            /** @var CatalogueImportRecord $record */
                            foreach ($record->images()->where('status', ImportImageStatus::PENDING->value)->get() as $image) {
                                /** @var CatalogueImportImage $image */
                                $action->approve($image, $actor);
                            }
                        }
                        Notification::make()->title('Bulk media approval complete')->success()->send();
                    }),
                \Filament\Actions\BulkAction::make('assignCategory')
                    ->label('Assign category')
                    ->form([
                        Forms\Components\Select::make('category_id')
                            ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $mappingService = app(CatalogueMappingService::class);
                        foreach ($records as $record) {
                            /** @var CatalogueImportRecord $record */
                            if ($record->workflow_status?->isTerminal()) {
                                continue;
                            }
                            $record->target_category_id = $data['category_id'];
                            if ($record->product) {
                                $record->product->update(['category_id' => $data['category_id']]);
                            }
                            $record->save();

                            $mapping = CatalogueCategoryMapping::query()
                                ->where('normalized_source_category', $mappingService->normalizeLabel((string) $record->source_category))
                                ->first();
                            if ($mapping) {
                                $mapping->target_category_id = $data['category_id'];
                                $mapping->status = ImportMappingStatus::APPROVED;
                                $mapping->approved_by = auth()->id();
                                $mapping->approved_at = now();
                                $mapping->save();
                            }
                        }
                    }),
                \Filament\Actions\BulkAction::make('assignBrand')
                    ->label('Assign brand')
                    ->form([
                        Forms\Components\Select::make('brand_id')
                            ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $mappingService = app(CatalogueMappingService::class);
                        foreach ($records as $record) {
                            /** @var CatalogueImportRecord $record */
                            if ($record->workflow_status?->isTerminal()) {
                                continue;
                            }
                            $record->target_brand_id = $data['brand_id'];
                            if ($record->product) {
                                $record->product->update(['brand_id' => $data['brand_id']]);
                            }
                            $record->save();

                            $mapping = CatalogueBrandMapping::query()
                                ->where('normalized_source_brand', $mappingService->normalizeLabel((string) $record->source_brand))
                                ->first();
                            if ($mapping) {
                                $mapping->target_brand_id = $data['brand_id'];
                                $mapping->status = ImportMappingStatus::APPROVED;
                                $mapping->approved_by = auth()->id();
                                $mapping->approved_at = now();
                                $mapping->save();
                            }
                        }
                    }),
                \Filament\Actions\BulkAction::make('rejectSelected')
                    ->label('Reject staging record')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function ($records, array $data) {
                        foreach ($records as $record) {
                            /** @var CatalogueImportRecord $record */
                            if ($record->workflow_status === ImportWorkflowStatus::PUBLISHED) {
                                continue;
                            }
                            $record->workflow_status = ImportWorkflowStatus::REJECTED;
                            $record->save();
                            app(AuditLogger::class)->log(
                                eventType: 'catalogue_import.rejected',
                                auditable: $record,
                                newValues: ['workflow_status' => ImportWorkflowStatus::REJECTED->value],
                                reason: $data['reason']
                            );
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogueImports::route('/'),
            'view' => Pages\ViewCatalogueImport::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['product', 'images']);
    }
}
