<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomepageFaqResource\Pages;
use App\Models\HomepageFaq;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class HomepageFaqResource extends Resource
{
    protected static ?string $model = HomepageFaq::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string | UnitEnum | null $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Homepage FAQs';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('question')->required()->maxLength(180),
            Forms\Components\Textarea::make('answer')->required()->rows(5)->maxLength(2000),
            Forms\Components\Toggle::make('is_published')->default(false),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')->searchable()->limit(60),
                Tables\Columns\IconColumn::make('is_published')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomepageFaqs::route('/'),
            'create' => Pages\CreateHomepageFaq::route('/create'),
            'edit' => Pages\EditHomepageFaq::route('/{record}/edit'),
        ];
    }
}
