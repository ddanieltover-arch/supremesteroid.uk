<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\Audit\AuditLogger;
use App\Services\Order\OrderStateMachine;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Fulfillment & Orders';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Order Summary')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_name_snapshot')
                            ->label('Customer')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_email_snapshot')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('shipping_method_name_snapshot')
                            ->label('Shipping')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_amount')
                            ->prefix('£')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('customer_notes')->disabled(),
                        Forms\Components\Textarea::make('admin_notes'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name_snapshot')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_email_snapshot')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('latestPayment.status')
                    ->label('Payment')
                    ->badge(),
                Tables\Columns\TextColumn::make('shipping_method_name_snapshot')
                    ->label('Shipping')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options([
                        'PENDING' => 'Pending',
                        'PAYMENT_SUBMITTED' => 'Submitted',
                        'UNDER_REVIEW' => 'Under review',
                        'PAID' => 'Paid',
                        'REJECTED' => 'Rejected',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('latestPayment', fn (Builder $q) => $q->where('status', $data['value']));
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('startProcessing')
                    ->label('Start processing')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->authorize(fn (Order $record) => auth()->user()?->can('updateStatus', $record))
                    ->visible(fn (Order $record) => $record->status === OrderStatus::PAID)
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        app(OrderStateMachine::class)->transition(
                            $record,
                            OrderStatus::PROCESSING,
                            auth()->user(),
                            'Fulfillment started from admin.'
                        );
                    }),
                Tables\Actions\Action::make('markShipped')
                    ->label('Mark shipped')
                    ->icon('heroicon-o-truck')
                    ->authorize(fn (Order $record) => auth()->user()?->can('updateStatus', $record))
                    ->visible(fn (Order $record) => $record->status === OrderStatus::PROCESSING)
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        app(OrderStateMachine::class)->transition(
                            $record,
                            OrderStatus::SHIPPED,
                            auth()->user(),
                            'Marked shipped from admin.'
                        );
                    }),
                Tables\Actions\Action::make('cancelOrder')
                    ->label('Cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->authorize(fn (Order $record) => auth()->user()?->can('cancel', $record))
                    ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_REVIEW, OrderStatus::PAID, OrderStatus::PROCESSING], true))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->label('Cancellation reason'),
                    ])
                    ->action(function (Order $record, array $data) {
                        try {
                            app(OrderStateMachine::class)->transition(
                                $record,
                                OrderStatus::CANCELLED,
                                auth()->user(),
                                $data['reason']
                            );
                        } catch (\Throwable $e) {
                            Notification::make()->title('Cannot cancel order')->body('This order cannot be cancelled in its current state.')->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['latestPayment', 'user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
