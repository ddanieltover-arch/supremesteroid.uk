<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'Catalogue Management';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->rows(3),
            Forms\Components\TextInput::make('image_url')->maxLength(255),
            Forms\Components\Select::make('parent_id')->relationship('parent', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('display_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_visible')->default(true),
            Forms\Components\Toggle::make('is_featured')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('display_order')->sortable(),
            Tables\Columns\IconColumn::make('is_visible')->boolean(),
            Tables\Columns\IconColumn::make('is_featured')->boolean(),
        ])->defaultSort('display_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
