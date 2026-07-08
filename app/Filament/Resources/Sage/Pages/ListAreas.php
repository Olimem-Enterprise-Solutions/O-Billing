<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\AreaResource;
use Filament\Resources\Pages\ListRecords;

class ListAreas extends ListRecords
{
    protected static string $resource = AreaResource::class;
}
