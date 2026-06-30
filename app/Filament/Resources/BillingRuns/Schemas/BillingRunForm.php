<?php

namespace App\Filament\Resources\BillingRuns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BillingRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('period_month')
                    ->label('Billing month')
                    ->helperText('Invoices are generated for this month.')
                    ->default(now()->startOfMonth())
                    ->required(),
                TextInput::make('description')
                    ->placeholder('e.g. June 2026 monthly rates run')
                    ->maxLength(255),
            ]);
    }
}
