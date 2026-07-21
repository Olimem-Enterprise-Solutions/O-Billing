<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\BillingRun;
use App\Models\SageOperation;
use App\Services\Sage\SageBillingRunPoster;
use App\Support\Currencies;
use RuntimeException;
use Throwable;

/**
 * Runs a billing run's Sage posting on the worker. Two modes via params['mode']:
 *  - 'preview' — a non-destructive dry-run whose summary the UI shows before the
 *    user confirms (docs, unresolved, totals, next invoice number).
 *  - 'post'    — the real, transactional post (params['force'] to override the
 *    double-post guard). Stamps the run's posting_status.
 */
final class PostBillingRun extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $run = BillingRun::withoutGlobalScopes()->findOrFail($operation->subject_id);
        $poster = app(SageBillingRunPoster::class);
        $mode = (string) ($operation->params['mode'] ?? 'preview');

        if ($mode === 'preview') {
            $build = $poster->preview($run);

            return [$this->summarize($build), $this->previewMessage($build)];
        }

        // Actual post.
        try {
            $build = $poster->post($run, (bool) ($operation->params['force'] ?? false));

            if (isset($build['error'])) {
                $run->forceFill(['posting_status' => 'failed'])->save();
                throw new RuntimeException($build['error']);
            }

            $run->forceFill(['posting_status' => 'posted', 'posted_at' => now()])->save();

            $usd = collect($build['by_token'] ?? [])->sum('usd_incl');
            $message = "Posted {$build['posted']} invoice(s) ({$build['invoice_from']} … {$build['invoice_to']}), "
                .Currencies::format((float) $usd, 'USD').'.';

            return [$this->summarize($build), $message];
        } catch (Throwable $e) {
            $run->forceFill(['posting_status' => 'failed'])->save();
            throw $e;
        }
    }

    protected function title(SageOperation $operation): string
    {
        return ($operation->params['mode'] ?? 'preview') === 'post'
            ? 'Post billing run to Sage'
            : 'Sage posting preview';
    }

    /**
     * Compact summary of a poster build — deliberately excludes the huge `docs`
     * array so it fits comfortably in the SageOperation.result JSON column.
     *
     * @param  array<string,mixed>  $build
     * @return array<string,mixed>
     */
    private function summarize(array $build): array
    {
        $unresolved = $build['unresolved'] ?? [];

        return [
            'database' => $build['database'] ?? null,
            'exchange_rate' => $build['exchange_rate'] ?? null,
            'tx_date' => $build['tx_date'] ?? null,
            'next_invoice_number' => $build['next_invoice_number'] ?? null,
            'document_count' => count($build['docs'] ?? []),
            'usd_total' => (float) collect($build['by_token'] ?? [])->sum('usd_incl'),
            'by_token' => $build['by_token'] ?? [],
            'unresolved_count' => count($unresolved),
            'unresolved' => array_slice(array_values($unresolved), 0, 25),
            'already_posted' => $build['already_posted'] ?? 0,
            'already_posted_example' => $build['already_posted_example'] ?? null,
            'posted' => $build['posted'] ?? null,
            'invoice_from' => $build['invoice_from'] ?? null,
            'invoice_to' => $build['invoice_to'] ?? null,
            'error' => $build['error'] ?? null,
        ];
    }

    /** @param array<string,mixed> $build */
    private function previewMessage(array $build): string
    {
        $usd = collect($build['by_token'] ?? [])->sum('usd_incl');
        $docs = count($build['docs'] ?? []);
        $parts = [
            "{$docs} Sage invoice(s) · ".Currencies::format((float) $usd, 'USD')
            .' (rate '.($build['exchange_rate'] ?? '?').') → '.($build['database'] ?? '')
            .', from '.($build['next_invoice_number'] ?? '?').'.',
        ];
        if (($n = count($build['unresolved'] ?? [])) > 0) {
            $parts[] = "{$n} charge(s) could not be matched and will be skipped.";
        }
        if (($p = (int) ($build['already_posted'] ?? 0)) > 0) {
            $parts[] = "{$p} invoice(s) already posted — posting again is blocked.";
        }

        return implode(' ', $parts);
    }
}
