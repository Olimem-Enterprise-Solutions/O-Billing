<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage\Pages;

use App\Filament\Resources\Sage\BillingRunResource;
use Filament\Resources\Pages\ListRecords;

class ListBillingRuns extends ListRecords
{
    protected static string $resource = BillingRunResource::class;
}
