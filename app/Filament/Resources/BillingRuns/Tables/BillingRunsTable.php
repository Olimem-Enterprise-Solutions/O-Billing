<?php

namespace App\Filament\Resources\BillingRuns\Tables;

use App\Models\BillingRun;
use App\Services\Billing\BillingRunService;
use App\Support\Currencies;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_month', 'desc')
            ->columns([
                TextColumn::make('period_month')
                    ->label('Month')
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'completed' ? 'success' : 'gray'),
                TextColumn::make('invoice_count')
                    ->label('Invoices')
                    ->badge(),
                TextColumn::make('currency_totals')
                    ->label('Total billed')
                    ->formatStateUsing(function ($state): string {
                        $totals = is_array($state) ? $state : (json_decode((string) $state, true) ?: []);
                        if ($totals === []) {
                            return '—';
                        }

                        return collect($totals)
                            ->map(fn ($amount, $currency) => Currencies::format($amount, $currency))
                            ->implode('  •  ');
                    }),
                TextColumn::make('run_at')
                    ->label('Generated')
                    ->dateTime()
                    ->placeholder('Not yet run')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label(fn (BillingRun $r) => $r->isCompleted() ? 'Re-run' : 'Generate invoices')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate billing run')
                    ->modalDescription('Creates an invoice for every active customer using the tariffs for their suburb, in their billing currency. Re-running replaces this run\'s existing invoices.')
                    ->action(function (BillingRun $record): void {
                        $result = app(BillingRunService::class)->generate($record);

                        $totals = collect($result['currency_totals'])
                            ->map(fn ($a, $c) => Currencies::format($a, $c))
                            ->implode(', ');

                        Notification::make()
                            ->success()
                            ->title('Billing run complete')
                            ->body("{$result['invoice_count']} invoices generated. Total billed: ".($totals ?: '—'))
                            ->send();
                    }),
                \Filament\Actions\ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
