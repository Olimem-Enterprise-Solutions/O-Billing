<?php

declare(strict_types=1);

namespace App\Filament\Resources\SageOperations\Pages;

use App\Filament\Resources\SageOperations\SageOperationResource;
use Filament\Resources\Pages\ListRecords;

class ListSageOperations extends ListRecords
{
    protected static string $resource = SageOperationResource::class;
}
