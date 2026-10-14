<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageFaqResource\Pages;

use App\Filament\Resources\HomepageFaqResource;
use Filament\Resources\Pages\EditRecord;

class EditHomepageFaq extends EditRecord
{
    protected static string $resource = HomepageFaqResource::class;
}
