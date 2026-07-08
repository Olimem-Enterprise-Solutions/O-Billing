<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\PropertyResource;
use Filament\Resources\Pages\ListRecords;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;
}
