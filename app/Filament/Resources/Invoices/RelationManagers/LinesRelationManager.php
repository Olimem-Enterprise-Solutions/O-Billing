<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\InvoiceLine;
use App\Support\Currencies;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Charges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description'),
                TextColumn::make('quantity')->numeric(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, InvoiceLine $r) => Currencies::format($state, $r->invoice->currency)),
                TextColumn::make('tax_amount')
                    ->label('Tax')
                    ->formatStateUsing(fn ($state, InvoiceLine $r) => Currencies::format($state, $r->invoice->currency)),
            ]);
    }
}
