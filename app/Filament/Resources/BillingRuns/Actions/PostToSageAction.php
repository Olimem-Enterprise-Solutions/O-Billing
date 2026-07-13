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
 * "Post to Sage" — stages a completed billing run as an UNPOSTED Sage debtors
 * batch via {@see SageBillingRunPoster}. The confirmation modal shows a live
 * dry-run preview (lines, totals, anything unresolved, double-billing guards);
 * confirming stages the batch, which a Sage operator then reviews and posts
 * inside Evolution so Sage itself writes the ledger double entry.
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
            ->modalSubmitActionLabel('Stage batch in Sage')
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
                        ->title('Not staged')
                        ->body($result['error'])
                        ->send();

                    return;
                }

                $usd = collect($result['by_token'])->sum('usd_incl');

                Notification::make()
                    ->success()
                    ->persistent()
                    ->title("Staged Sage batch {$result['batch_no']}")
                    ->body(
                        number_format($result['staged']).' lines, '.Currencies::format($usd, 'USD')
                        .' — UNPOSTED. In Sage Evolution: Debtors → Transactions → Batch Entry → open '
                        .$result['batch_no'].' → review → Validate → Post.'
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
        $parts[] = '<strong>'.number_format(count($p['lines'])).'</strong> charge lines · <strong>'
            .Currencies::format($usd, 'USD').'</strong> (rate '.$p['exchange_rate'].' ZWG/USD) → batch <strong>'
            .e($p['batch_no']).'</strong> in '.e($p['database']).'.';

        if ($p['unresolved'] !== []) {
            $parts[] = '<strong>'.count($p['unresolved']).' line(s) have no matching Sage account</strong> and will be skipped.';
        }
        if ($p['already_staged']) {
            $parts[] = '<strong>An unposted batch for this run already exists in Sage</strong> — staging again is blocked until it is posted or deleted there.';
        }
        if ($p['previously_posted']) {
            $parts[] = '<strong>This run was already posted in Sage before</strong> — staging it again would double-bill once posted.';
        }

        $parts[] = 'The batch is staged <strong>unposted</strong>: a Sage operator reviews and posts it in Evolution (Debtors → Batch Entry), which writes the debtor / revenue / VAT double entry.';

        return new HtmlString(implode('<br><br>', $parts));
    }
}
