<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageContentResource\Pages;

use App\Filament\Resources\HomepageContentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomepageContents extends ListRecords
{
    protected static string $resource = HomepageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
