<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sku')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing & Compliance')
                    ->schema([
                        Forms\Components\TextInput::make('price_amount')
                            ->numeric()
                            ->prefix('£')
                            ->required(),
                        Forms\Components\TextInput::make('compare_at_price_amount')
                            ->numeric()
                            ->prefix('£'),
                        Forms\Components\Select::make('status')
                            ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()]))
                            ->required(),
                        Forms\Components\Select::make('classification')
                            ->options(collect(ProductClassification::cases())->mapWithKeys(fn ($cls) => [$cls->value => $cls->label()]))
                            ->required(),
                        Forms\Components\TextInput::make('batch_number')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('lab_verification_reference')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Descriptions & Safety')
                    ->schema([
                        Forms\Components\Textarea::make('short_description')
                            ->rows(3),
                        Forms\Components\RichEditor::make('description'),
                        Forms\Components\Textarea::make('safety_guidelines')
                            ->rows(3)
                            ->helperText('Approved lawful safety guidance only. Do not include dosing cycles or medical claims.'),
                    ]),

                Forms\Components\Repeater::make('images')
                    ->relationship()
                    ->schema([
                        Forms\Components\FileUpload::make('url')
                            ->label('Image')
                            ->disk(fn (): string => (string) config('filesystems.cloud', 'public'))
                            ->directory('product_images')
                            ->visibility('public')
                            ->image()
                            ->maxSize((int) config('filesystems.uploads.max_kilobytes', 10240))
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->getUploadedFileNameUsing(fn (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string => (string) \Illuminate\Support\Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'))
                            ->required(),
                        Forms\Components\TextInput::make('alt_text')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('display_order')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_primary'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_amount')
                    ->money('GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('classification')
                    ->badge(),
                Tables\Columns\TextColumn::make('inventory.quantity_on_hand')
                    ->label('On Hand')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->state(fn (Product $record) => $record->inventory?->available() ?? 0)
                    ->badge()
                    ->color(fn (Product $record) => ($record->inventory?->available() ?? 0) <= 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('compliance_verified_at')
                    ->dateTime()
                    ->label('Verified On')
                    ->placeholder('Unverified'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                Tables\Filters\SelectFilter::make('classification')
                    ->options(collect(ProductClassification::cases())->mapWithKeys(fn ($cls) => [$cls->value => $cls->label()])),
                Tables\Filters\TernaryFilter::make('compliance_verified_at')
                    ->label('Compliance verified')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('verifyCompliance')
                    ->label('Verify Compliance')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (Product $record) => auth()->user()?->can('verifyCompliance', $record))
                    ->visible(fn (Product $record) => $record->compliance_verified_at === null)
                    ->action(function (Product $record) {
                        $record->compliance_verified_at = now();
                        $record->save();

                        app(AuditLogger::class)->log(
                            eventType: 'product_approval',
                            auditable: $record,
                            oldValues: null,
                            newValues: ['compliance_verified_at' => (string) $record->compliance_verified_at],
                            reason: 'Admin manually verified compliance.'
                        );
                    }),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->authorize(fn (Product $record) => auth()->user()?->can('update', $record))
                    ->visible(fn (Product $record) => $record->status !== ProductStatus::ACTIVE)
                    ->action(function (Product $record) {
                        $previous = $record->status;
                        $record->status = ProductStatus::ACTIVE;
                        if ($record->compliance_verified_at === null) {
                            $record->compliance_verified_at = now();
                        }

                        $check = app(ProductPurchaseEligibilityService::class)
                            ->checkEligibility($record, null, 1, false);

                        if (! $check['is_eligible']) {
                            Notification::make()
                                ->title('Product cannot be published')
                                ->body(implode(' ', $check['reasons']))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->save();

                        app(AuditLogger::class)->log(
                            eventType: 'product_publishing',
                            auditable: $record,
                            oldValues: ['status' => $previous?->value],
                            newValues: ['status' => ProductStatus::ACTIVE->value],
                            reason: 'Admin published product to storefront.'
                        );
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('inventory');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
