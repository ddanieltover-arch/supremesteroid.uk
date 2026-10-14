<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ProductClassification;
use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\CataloguePublicationService;
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
                Forms\Components\Section::make('Overview')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sku')
                            ->label(fn ($record) => $record?->isInternalSku() ? 'Internal Identifier / SKU' : 'SKU')
                            ->helperText(fn ($record) => $record?->isInternalSku()
                                ? 'Internal database identifier (IMP-...). Source published no SKU. Replace with genuine merchant SKU if established.'
                                : 'Supplier / merchant SKU.')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Toggle::make('is_featured'),
                        Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_demo')
                            ->helperText('Demo products never become purchasable.'),
                        Forms\Components\Toggle::make('image_not_required')
                            ->label('IMAGE_NOT_REQUIRED')
                            ->helperText('Explicit override when a lawful product has no image. Do not use to bypass broken media.'),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price_amount')
                            ->numeric()
                            ->prefix('£')
                            ->required(),
                        Forms\Components\TextInput::make('compare_at_price_amount')
                            ->numeric()
                            ->prefix('£'),
                        Forms\Components\TextInput::make('currency')->default('GBP')->maxLength(3),
                    ])->columns(3),

                Forms\Components\Section::make('Brand')
                    ->schema([
                        Forms\Components\Select::make('brand_id')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Forms\Components\Section::make('Categories')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Main category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('categories')
                            ->label('Additional categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->helperText('Only assign extra categories when they genuinely describe the product.'),
                    ])->columns(2),

                Forms\Components\Section::make('Compliance')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()]))
                            ->required(),
                        Forms\Components\Select::make('classification')
                            ->options(collect(ProductClassification::cases())->mapWithKeys(fn ($cls) => [$cls->value => $cls->label()]))
                            ->required(),
                        Forms\Components\TextInput::make('batch_number')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('lab_verification_reference')
                            ->maxLength(255)
                            ->helperText('Show a verification badge only when this documented reference exists.'),
                        Forms\Components\DateTimePicker::make('compliance_verified_at')->disabled(),
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

                Forms\Components\Section::make('Product images')
                    ->description('Product-level gallery. Used when a selected variant has no dedicated images.')
                    ->schema([
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
                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variant assignment')
                                    ->options(function (): array {
                                        try {
                                            $livewire = \Livewire\Livewire::current();
                                            $product = $livewire?->getRecord();
                                        } catch (\Throwable) {
                                            $product = null;
                                        }
                                        if (! $product instanceof Product) {
                                            return [];
                                        }

                                        return $product->variants()
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn ($variant) => [
                                                $variant->id => $variant->name.' ('.$variant->sku.')',
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->nullable()
                                    ->helperText('Leave empty for product-level gallery. Only variants of this product may be selected.'),
                                Forms\Components\TextInput::make('alt_text')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('display_order')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Toggle::make('is_primary'),
                                Forms\Components\TextInput::make('caption')->maxLength(255),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): string => filled($state['product_variant_id'] ?? null)
                                ? 'Variant image'
                                : 'Product image'),
                    ]),
                Forms\Components\Section::make('Variants')
                    ->schema([
                        Forms\Components\Repeater::make('variants')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('sku')->required(),
                                Forms\Components\TextInput::make('price_amount')->numeric()->prefix('£')->required(),
                                Forms\Components\TextInput::make('compare_at_price_amount')->numeric()->prefix('£'),
                                Forms\Components\TextInput::make('weight_grams')->numeric(),
                                Forms\Components\Toggle::make('is_active')->default(true),
                            ])
                            ->columns(3)
                            ->helperText('Zero, one, or many variants are supported. SKUs and attribute sets must stay unique.'),
                    ]),

                Forms\Components\Section::make('Inventory')
                    ->schema([
                        Forms\Components\Placeholder::make('inventory_note')
                            ->content('On-hand quantities are managed in Inventory. Customer pages never expose exact counts unless configured.'),
                    ]),

                Forms\Components\Section::make('Content')
                    ->relationship('content')
                    ->schema([
                        Forms\Components\TagsInput::make('highlights')
                            ->helperText('3–6 factual highlights. Do not add medical or performance claims.'),
                        Forms\Components\Textarea::make('overview')->rows(3),
                        Forms\Components\Textarea::make('composition')->rows(2),
                        Forms\Components\Textarea::make('manufacturer_information')->rows(2),
                        Forms\Components\Textarea::make('packaging_information')->rows(2),
                        Forms\Components\Textarea::make('storage_information')->rows(2),
                        Forms\Components\Textarea::make('shipping_information')->rows(2),
                        Forms\Components\Textarea::make('documentation')->rows(3)
                            ->helperText('Record only documentation that actually exists. Do not invent certificates or lab results.'),
                        Forms\Components\Textarea::make('important_information')->rows(2)
                            ->helperText('Disclaimers and lawful product notices only. No dosing or cycle advice.'),
                    ]),

                Forms\Components\Section::make('Related products')
                    ->schema([
                        Forms\Components\Repeater::make('relations')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('related_product_id')
                                    ->label('Related product')
                                    ->options(fn () => Product::query()
                                        ->purchasable()
                                        ->orderBy('name')
                                        ->limit(200)
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->helperText('Only active, published, purchasable products appear here.'),
                                Forms\Components\Select::make('relation_type')
                                    ->options(collect(ProductRelationType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->name]))
                                    ->default(ProductRelationType::RELATED->value)
                                    ->required(),
                                Forms\Components\TextInput::make('display_order')->numeric()->default(0),
                            ])
                            ->columns(3),
                    ]),

                Forms\Components\Section::make('SEO')
                    ->relationship('seoMetadata')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')->maxLength(70)
                            ->helperText('Defaults: {Product Name} | {Brand} | Supreme Steroids'),
                        Forms\Components\Textarea::make('meta_description')->rows(2)->maxLength(160),
                        Forms\Components\TextInput::make('canonical_url'),
                        Forms\Components\TextInput::make('robots_directive')->default('index,follow'),
                        Forms\Components\TextInput::make('og_title'),
                        Forms\Components\Textarea::make('og_description')->rows(2),
                        Forms\Components\TextInput::make('og_image_url'),
                    ]),

                Forms\Components\Section::make('Publishing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('published_at'),
                        Forms\Components\Placeholder::make('publication_gate')
                            ->content(function ($component): string {
                                $record = method_exists($component, 'getRecord') ? $component->getRecord() : null;
                                if (! $record instanceof Product) {
                                    return 'Use the Publish action. It runs CataloguePublicationChecklistService and will not bypass compliance review.';
                                }
                                $checklist = app(CataloguePublicationService::class)->checklist($record);
                                $lines = collect($checklist['items'])
                                    ->map(fn (array $item) => ($item['passed'] ? 'PASS' : 'FAIL').' — '.$item['label'].($item['passed'] ? '' : ': '.$item['message']))
                                    ->implode("\n");

                                return ($checklist['can_publish'] ? "READY\n\n" : "BLOCKED\n\n").$lines;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU / Identifier')
                    ->description(fn (Product $record): ?string => $record->isInternalSku() ? 'Internal placeholder' : 'Genuine SKU')
                    ->badge(fn (Product $record): bool => $record->isInternalSku())
                    ->color(fn (Product $record): string => $record->isInternalSku() ? 'warning' : 'gray')
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
                \Filament\Actions\Action::make('verifyCompliance')
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
                \Filament\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->authorize(fn (Product $record) => auth()->user()?->can('update', $record))
                    ->visible(fn (Product $record) => $record->status !== ProductStatus::ACTIVE)
                    ->action(function (Product $record) {
                        try {
                            app(CataloguePublicationService::class)->publish($record);
                        } catch (ProductNotPurchasableException $exception) {
                            Notification::make()
                                ->title('Product cannot be published')
                                ->body(implode(' ', $exception->getReasons() ?: [$exception->getMessage()]))
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('assignCategory')
                        ->label('Assign category')
                        ->form([
                            Forms\Components\Select::make('category_id')
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['category_id' => $data['category_id']]);
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
                            foreach ($records as $record) {
                                $record->update(['brand_id' => $data['brand_id']]);
                            }
                        }),
                    \Filament\Actions\BulkAction::make('archive')
                        ->label('Archive')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => ProductStatus::ARCHIVED]);
                            }
                        }),
                    \Filament\Actions\DeleteBulkAction::make(),
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
