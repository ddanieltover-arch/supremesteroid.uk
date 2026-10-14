<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Services\Catalogue\ReviewService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue Management';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer'),
                Tables\Columns\TextColumn::make('rating'),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\IconColumn::make('is_verified_purchase')->boolean()->label('Verified'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::APPROVED)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderate($record, ReviewStatus::APPROVED, auth()->user())),
                \Filament\Actions\Action::make('reject')
                    ->color('danger')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::REJECTED)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderate($record, ReviewStatus::REJECTED, auth()->user())),
                \Filament\Actions\Action::make('hide')
                    ->color('warning')
                    ->visible(fn (Review $record) => $record->status !== ReviewStatus::HIDDEN)
                    ->action(fn (Review $record) => app(ReviewService::class)->moderate($record, ReviewStatus::HIDDEN, auth()->user())),
                \Filament\Actions\Action::make('restore')
                    ->visible(fn (Review $record) => in_array($record->status, [ReviewStatus::HIDDEN, ReviewStatus::REJECTED], true))
                    ->action(fn (Review $record) => app(ReviewService::class)->moderate($record, ReviewStatus::PENDING, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
        ];
    }
}
