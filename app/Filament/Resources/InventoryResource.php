<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Stock Levels')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->relationship('product', 'name')
                            ->disabled(),
                        Forms\Components\TextInput::make('quantity_on_hand')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('quantity_reserved')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('safety_stock_threshold')
                            ->numeric()
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Available')
                    ->state(fn (Inventory $record) => max(0, $record->quantity_on_hand - $record->quantity_reserved))
                    ->badge()
                    ->color(fn (Inventory $record) => ($record->quantity_on_hand - $record->quantity_reserved) <= $record->safety_stock_threshold ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('safety_stock_threshold')
                    ->label('Safety Stock')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('adjustStock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->authorize(fn (Inventory $record) => auth()->user()?->can('adjust', $record))
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Adjustment Type')
                            ->options([
                                'ADD' => 'Add Stock (+)',
                                'DEDUCT' => 'Deduct Stock (-)',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason / Reference Note')
                            ->required()
                            ->placeholder('e.g. Inbound batch arrival / damaged goods audit'),
                    ])
                    ->action(function (Inventory $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $change = (int) $data['quantity'];
                            $delta = $data['type'] === 'ADD' ? $change : -$change;
                            $oldOnHand = $record->quantity_on_hand;

                            $record->quantity_on_hand = max(0, $record->quantity_on_hand + $delta);
                            $record->save();

                            InventoryMovement::create([
                                'inventory_id' => $record->id,
                                'movement_type' => 'adjustment',
                                'quantity_change' => $delta,
                                'reference_type' => 'manual_admin_adjustment',
                                'reference_id' => auth()->id(),
                                'actor_id' => auth()->id(),
                                'notes' => $data['reason'],
                            ]);

                            app(AuditLogger::class)->log(
                                eventType: 'inventory_adjustment',
                                auditable: $record,
                                oldValues: ['quantity_on_hand' => $oldOnHand],
                                newValues: ['quantity_on_hand' => $record->quantity_on_hand, 'delta' => $delta],
                                reason: $data['reason']
                            );
                        });
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
        ];
    }
}
