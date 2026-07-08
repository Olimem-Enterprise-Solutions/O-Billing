<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Services\Sage\SageAdHocBillingWriter;
use App\Support\Currencies;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Property')->searchable(),
                TextColumn::make('customer.area.name')->label('Suburb')->toggleable(),
                TextColumn::make('period_month')->label('Month')->date('M Y')->sortable(),
                TextColumn::make('subtotal')
                    ->formatStateUsing(fn ($state, Invoice $r) => Currencies::format($state, $r->currency)),
                TextColumn::make('tax_total')
                    ->label('Tax')
                    ->formatStateUsing(fn ($state, Invoice $r) => Currencies::format($state, $r->currency)),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state, Invoice $r) => Currencies::format($state, $r->currency))
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('currency')->badge(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('billing_run_id')
                    ->label('Billing run')
                    ->relationship('billingRun', 'period_month'),
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'issued' => 'Issued',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                self::pushToSageAction(),
            ]);
    }

    /**
     * Stage the invoice in Sage as pending ad-hoc billing (Sage then Calculates &
     * Processes it to post). Writes to the configured write database — the test
     * company by default — never straight to the ledger.
     */
    private static function pushToSageAction(): Action
    {
        return Action::make('pushToSage')
            ->label('Push to Sage')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Push invoice to Sage')
            ->modalDescription(fn (): string => 'Stages this invoice as pending ad-hoc billing in "'
                .config('database.connections.sage_write.database')
                .'". A Sage operator then Calculates & Processes it to post the charge — nothing posts automatically.')
            ->modalSubmitActionLabel('Stage in Sage')
            ->action(function (Invoice $record): void {
                $result = app(SageAdHocBillingWriter::class)
                    ->pushInvoice($record->load(['lines.service', 'customer']));

                if (! ($result['ok'] ?? false)) {
                    Notification::make()->danger()->title('Push failed')
                        ->body($result['error'] ?? 'Unknown error')->persistent()->send();

                    return;
                }

                $entries = collect($result['batches'])->sum('entries');
                Notification::make()->success()->title('Staged in Sage')
                    ->body("{$entries} charge line(s) staged in \"{$result['database']}\" as ad-hoc billing. Calculate & Process the batch in Sage to post it.")
                    ->persistent()->send();

                if (! empty($result['unresolved'])) {
                    Notification::make()->warning()->title('Some lines skipped')
                        ->body('No matching Sage service for: '.implode('; ', $result['unresolved']))
                        ->persistent()->send();
                }
            });
    }
}
