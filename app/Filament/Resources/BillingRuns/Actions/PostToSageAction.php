<?php

declare(strict_types=1);

namespace App\Filament\Resources\BillingRuns\Actions;

use App\Jobs\Sage\PostBillingRun;
use App\Models\BillingRun;
use App\Support\Sage\SageBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * "Post to Sage" — queues a completed billing run to be posted into Sage
 * Evolution by the on-site worker (via {@see PostBillingRun}). The cloud app has
 * no Sage connection, so it records a SageOperation and dispatches the job; the
 * worker posts the invoice documents + debtor and GL entries and notifies the
 * user. The poster's double-post guard makes re-queuing safe.
 */
final class PostToSageAction
{
    public static function make(): Action
    {
        return Action::make('postToSage')
            ->label(fn (BillingRun $record) => $record->posting_status === 'posting' ? 'Posting queued…' : 'Post to Sage')
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->color('info')
            ->visible(fn (BillingRun $record) => $record->isCompleted() && $record->posting_status !== 'posted')
            ->disabled(fn (BillingRun $record) => $record->posting_status === 'posting')
            ->requiresConfirmation()
            ->modalHeading('Post billing run to Sage')
            ->modalDescription(new HtmlString(
                'This queues the run to be posted to Sage by the on-site worker: each ratepayer account is '
                .'<strong>debited</strong> and the service revenue accounts and VAT control are <strong>credited</strong>. '
                .'Posting is <strong>irreversible</strong>; the double-post guard prevents duplicates. '
                .'You will be notified when the worker finishes.'
            ))
            ->modalSubmitActionLabel('Queue posting')
            ->action(function (BillingRun $record): void {
                $record->forceFill(['posting_status' => 'posting'])->save();

                SageBridge::queue('post_run', PostBillingRun::class, $record, ['mode' => 'post']);

                Notification::make()
                    ->success()
                    ->title('Posting queued')
                    ->body('The run has been queued to post to Sage. You will be notified when it completes; watch progress under Sage → Sage Operations.')
                    ->send();
            });
    }
}
