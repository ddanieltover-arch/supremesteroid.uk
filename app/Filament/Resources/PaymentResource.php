<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static string | UnitEnum | null $navigationGroup = 'Financial Review';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->relationship('order', 'order_number')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->prefix('£')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()]))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('viewProof')
                    ->label('View proof')
                    ->icon('heroicon-o-document')
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record) ?? false)
                    ->visible(fn (Payment $record) => $record->submissions()->whereNotNull('proof_file_id')->exists())
                    ->url(fn (Payment $record) => route('orders.payment.proof.download', [$record->order_id, $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve Payment')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => in_array($record->status, [PaymentStatus::PAYMENT_SUBMITTED, PaymentStatus::UNDER_REVIEW], true))
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record))
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Approval Notes')
                            ->placeholder('e.g. Bank statement verified / blockchain tx confirmed'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        app(\App\Services\Payment\PaymentStateMachine::class)->transition(
                            payment: $record,
                            newStatus: PaymentStatus::PAID,
                            actor: auth()->user(),
                            reason: $data['admin_notes'] ?? 'Approved via Filament Admin'
                        );
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject Payment')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => ! in_array($record->status, [PaymentStatus::PAID, PaymentStatus::REJECTED, PaymentStatus::REFUNDED], true))
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('e.g. Reference mismatch / funds not received'),
                    ])
                    ->action(function (Payment $record, array $data) {
                        app(\App\Services\Payment\PaymentStateMachine::class)->transition(
                            payment: $record,
                            newStatus: PaymentStatus::REJECTED,
                            actor: auth()->user(),
                            reason: $data['reason']
                        );
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
