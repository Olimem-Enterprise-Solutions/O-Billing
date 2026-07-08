<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\ServiceGroupResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceGroups extends ListRecords
{
    protected static string $resource = ServiceGroupResource::class;
}
