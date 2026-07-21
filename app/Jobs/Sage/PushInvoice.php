<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\Invoice;
use App\Models\SageOperation;
use App\Services\Sage\SageAdHocBillingWriter;
use App\Services\Sage\SageBillingRunPoster;
use App\Support\Currencies;
use RuntimeException;

/**
 * Sends a single invoice to Sage in whichever shape the company expects, decided
 * on the worker (the cloud app can't probe Sage):
 *  - property-module companies → staged as pending ad-hoc billing;
 *  - debtors-ledger companies → posted straight in as posted invoice documents.
 */
final class PushInvoice extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->with(['lines.service.serviceType', 'customer'])
            ->findOrFail($operation->subject_id);

        return app(SageAdHocBillingWriter::class)->targetsAdHocBilling()
            ? $this->stage($invoice)
            : $this->post($invoice);
    }

    /** @return array{0: array<string,mixed>, 1: string} */
    private function stage(Invoice $invoice): array
    {
        $result = app(SageAdHocBillingWriter::class)->pushInvoice($invoice);

        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Push failed.');
        }

        $entries = collect($result['batches'] ?? [])->sum('entries');
        $message = "{$entries} charge line(s) staged as ad-hoc billing — Calculate & Process the batch in Sage to post it.";

        return [$result, $message];
    }

    /** @return array{0: array<string,mixed>, 1: string} */
    private function post(Invoice $invoice): array
    {
        $build = app(SageBillingRunPoster::class)->postInvoice($invoice);

        if (isset($build['error'])) {
            throw new RuntimeException($build['error']);
        }

        $usd = collect($build['by_token'] ?? [])->sum('usd_incl');
        $range = $build['invoice_from'] === $build['invoice_to']
            ? "Document {$build['invoice_from']}"
            : "Documents {$build['invoice_from']} … {$build['invoice_to']}";

        unset($build['docs']); // keep the stored result compact (headers/lines are bulky)

        return [
            $build,
            "{$range}, ".Currencies::format((float) $usd, 'USD').' — debtor debited, revenue and VAT credited.',
        ];
    }

    protected function title(SageOperation $operation): string
    {
        return 'Push invoice to Sage';
    }
}
