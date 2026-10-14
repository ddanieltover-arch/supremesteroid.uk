<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomepageContentResource\Pages;
use App\Models\HomepageContent;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class HomepageContentResource extends Resource
{
    protected static ?string $model = HomepageContent::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static string | UnitEnum | null $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Homepage';

    public static function canCreate(): bool
    {
        return HomepageContent::query()->doesntExist();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Announcement')->schema([
                Forms\Components\Toggle::make('announcement_enabled')->default(true),
                Forms\Components\Toggle::make('announcement_use_shipping_threshold')->default(true),
                Forms\Components\TextInput::make('announcement_message')->maxLength(180),
            ]),
            Forms\Components\Section::make('Hero')->schema([
                Forms\Components\TextInput::make('hero_eyebrow')->maxLength(80),
                Forms\Components\TextInput::make('hero_headline')->maxLength(140),
                Forms\Components\Textarea::make('hero_description')->rows(3)->maxLength(400),
                Forms\Components\TextInput::make('hero_primary_label')->maxLength(40),
                Forms\Components\TextInput::make('hero_primary_url')->maxLength(255),
                Forms\Components\TextInput::make('hero_secondary_label')->maxLength(40),
                Forms\Components\TextInput::make('hero_secondary_url')->maxLength(255),
            ])->columns(2),
            Forms\Components\Section::make('Editorial')->schema([
                Forms\Components\Toggle::make('editorial_enabled')->default(true),
                Forms\Components\TextInput::make('editorial_label')->maxLength(80),
                Forms\Components\TextInput::make('editorial_heading')->maxLength(140),
                Forms\Components\Textarea::make('editorial_body')->rows(4)->maxLength(600),
                Forms\Components\TextInput::make('editorial_cta_label')->maxLength(40),
                Forms\Components\TextInput::make('editorial_cta_url')->maxLength(255),
                Forms\Components\TextInput::make('editorial_image_url')->maxLength(255),
            ])->columns(2),
            Forms\Components\Section::make('Newsletter & SEO')->schema([
                Forms\Components\Toggle::make('newsletter_enabled')->default(true),
                Forms\Components\TextInput::make('newsletter_heading')->maxLength(120),
                Forms\Components\Textarea::make('newsletter_body')->rows(2)->maxLength(300),
                Forms\Components\TextInput::make('seo_title')->maxLength(80),
                Forms\Components\Textarea::make('seo_description')->rows(2)->maxLength(160),
            ])->columns(2),
            Forms\Components\Section::make('Section order')->schema([
                Forms\Components\Repeater::make('section_order')
                    ->schema([
                        Forms\Components\TextInput::make('key')->required()->maxLength(40),
                        Forms\Components\TextInput::make('position')->numeric()->required(),
                        Forms\Components\Toggle::make('enabled')->default(true),
                    ])
                    ->columns(3)
                    ->default(config('homepage.sections')),
            ]),
            Forms\Components\Section::make('Social links')->schema([
                Forms\Components\KeyValue::make('social_links')
                    ->keyLabel('Network')
                    ->valueLabel('URL')
                    ->helperText('Leave empty to hide an icon. Only http(s) URLs are used.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\IconColumn::make('announcement_enabled')->boolean(),
            Tables\Columns\TextColumn::make('hero_headline')->limit(40),
            Tables\Columns\IconColumn::make('newsletter_enabled')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomepageContents::route('/'),
            'create' => Pages\CreateHomepageContent::route('/create'),
            'edit' => Pages\EditHomepageContent::route('/{record}/edit'),
        ];
    }
}
