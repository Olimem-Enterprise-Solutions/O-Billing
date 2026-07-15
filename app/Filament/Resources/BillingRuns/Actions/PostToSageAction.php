<?php

declare(strict_types=1);

namespace App\Filament\Resources\BillingRuns\Actions;

use App\Models\BillingRun;
use App\Services\Sage\SageBillingRunPoster;
use App\Support\Currencies;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * "Post to Sage" — posts a completed billing run straight into Sage Evolution
 * as POSTED customer invoices via {@see SageBillingRunPoster}: an invoice
 * document per charge plus the debtor transaction and balanced GL double entry
 * (debtors control ↔ revenue ↔ VAT). The confirmation modal shows a live
 * dry-run preview (documents, totals, anything unresolved, double-post guard).
 */
final class PostToSageAction
{
    public static function make(): Action
    {
        return Action::make('postToSage')
            ->label('Post to Sage')
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->color('info')
            ->visible(fn (BillingRun $record) => $record->isCompleted())
            ->requiresConfirmation()
            ->modalHeading('Post billing run to Sage')
            ->modalDescription(fn (BillingRun $record) => self::previewSummary($record))
            ->modalSubmitActionLabel('Post invoices to Sage')
            ->action(function (BillingRun $record): void {
                try {
                    $result = app(SageBillingRunPoster::class)->post($record);
                } catch (Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Posting to Sage failed')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                if (isset($result['error'])) {
                    Notification::make()
                        ->warning()
                        ->title('Not posted')
                        ->body($result['error'])
                        ->send();

                    return;
                }

                $usd = collect($result['by_token'])->sum('usd_incl');

                Notification::make()
                    ->success()
                    ->persistent()
                    ->title("Posted {$result['posted']} invoices to Sage")
                    ->body(
                        "Documents {$result['invoice_from']} … {$result['invoice_to']}, "
                        .Currencies::format($usd, 'USD')
                        .'. Debtors debited, revenue and VAT credited — statements, trial balance and'
                        .' account enquiries in Sage Evolution reflect the run immediately.'
                    )
                    ->send();
            });
    }

    /** The live dry-run summary shown in the confirmation modal. */
    private static function previewSummary(BillingRun $record): HtmlString
    {
        try {
            $p = app(SageBillingRunPoster::class)->preview($record);
        } catch (Throwable $e) {
            return new HtmlString('<strong>Could not reach Sage:</strong> '.e($e->getMessage()));
        }

        $usd = collect($p['by_token'])->sum('usd_incl');

        $parts = [];
        $parts[] = '<strong>'.number_format(count($p['docs'])).'</strong> Sage invoices · <strong>'
            .Currencies::format($usd, 'USD').'</strong> (rate '.$p['exchange_rate'].' ZWG/USD) → '
            .e($p['database']).', numbered from <strong>'.e($p['next_invoice_number']).'</strong>.';

        if ($p['unresolved'] !== []) {
            $parts[] = '<strong>'.count($p['unresolved']).' charge(s) could not be matched to a Sage account/item</strong> and will be skipped.';
        }
        if ($p['already_posted'] > 0) {
            $parts[] = '<strong>'.number_format($p['already_posted']).' invoice(s) of this run are already posted in Sage</strong> — posting again is blocked because it would double-bill.';
        }

        $parts[] = 'Posting writes the invoice documents and the balanced double entry directly: '
            .'each ratepayer account is <strong>debited</strong>, the service revenue accounts and VAT control are '
            .'<strong>credited</strong>, and statements, the trial balance and account enquiries update immediately.';

        return new HtmlString(implode('<br><br>', $parts));
    }
}
