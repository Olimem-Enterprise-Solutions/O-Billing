<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\ClientResource;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;
}
