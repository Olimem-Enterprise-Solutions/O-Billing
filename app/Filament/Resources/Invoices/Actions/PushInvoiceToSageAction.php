<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Actions;

use App\Jobs\Sage\PushInvoice;
use App\Models\Invoice;
use App\Support\Sage\SageBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Queues a single invoice to be sent to Sage by the on-site worker (via
 * {@see PushInvoice}). The worker decides the shape per company — staged ad-hoc
 * billing for property-module companies, or a posted invoice document for
 * debtors-ledger companies — since only it can probe the Sage schema.
 */
final class PushInvoiceToSageAction
{
    public static function make(): Action
    {
        return Action::make('pushToSage')
            ->label('Push to Sage')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Push invoice to Sage')
            ->modalDescription('Queues this invoice to be sent to Sage by the on-site worker — staged for a Sage operator to process, or posted directly, depending on the council\'s Sage setup. You will be notified when it completes.')
            ->modalSubmitActionLabel('Queue push')
            ->action(function (Invoice $record): void {
                SageBridge::queue('push_invoice', PushInvoice::class, $record);

                Notification::make()
                    ->success()
                    ->title('Push to Sage queued')
                    ->body('The invoice has been queued to be sent to Sage. Watch progress under Sage → Sage Operations.')
                    ->send();
            });
    }
}
