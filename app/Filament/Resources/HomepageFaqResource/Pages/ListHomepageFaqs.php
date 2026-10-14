<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageFaqResource\Pages;

use App\Filament\Resources\HomepageFaqResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomepageFaqs extends ListRecords
{
    protected static string $resource = HomepageFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
