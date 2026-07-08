<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Sage\SageAdHocBillingWriter;
use Illuminate\Console\Command;

class SagePushInvoice extends Command
{
    protected $signature = 'sage:push-invoice {invoice : O-Billing invoice id or number}';

    protected $description = 'Stage an O-Billing invoice in Sage as a pending ad-hoc billing batch (for Sage to Calculate & Process)';

    public function handle(SageAdHocBillingWriter $writer): int
    {
        $arg = (string) $this->argument('invoice');
        $invoice = Invoice::with(['lines.service', 'customer'])
            ->where('id', $arg)->orWhere('invoice_number', $arg)->first();

        if ($invoice === null) {
            $this->error("Invoice '{$arg}' not found.");

            return self::FAILURE;
        }

        $this->info("Pushing invoice {$invoice->invoice_number} — {$invoice->customer->name} ({$invoice->lines->count()} lines) …");

        $result = $writer->pushInvoice($invoice);

        if (! ($result['ok'] ?? false)) {
            $this->error($result['error'] ?? 'Push failed.');

            return self::FAILURE;
        }

        $this->info("Staged in: {$result['database']}  (Sage period {$result['period_id']})");
        foreach ($result['batches'] as $b) {
            $this->line("  • ad-hoc batch #{$b['batch_id']} — service {$b['service_id']} — {$b['entries']} entr(ies)");
        }
        if (! empty($result['unresolved'])) {
            $this->warn('Lines with no matching Sage property-service (skipped): '.implode('; ', $result['unresolved']));
        }
        $this->newLine();
        $this->info('Done. In Sage, open Ad-Hoc Billing, then Calculate & Process the batch to post it.');

        return self::SUCCESS;
    }
}
