<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageContentResource\Pages;

use App\Filament\Resources\HomepageContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomepageContent extends CreateRecord
{
    protected static string $resource = HomepageContentResource::class;
}
