<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\TariffResource;
use Filament\Resources\Pages\ListRecords;

class ListTariffs extends ListRecords
{
    protected static string $resource = TariffResource::class;
}
