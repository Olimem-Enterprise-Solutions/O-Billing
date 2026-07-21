<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\SageOperation;
use App\Services\Sage\SageLedgerImportService;
use Illuminate\Support\Str;

/** Imports ratepayers (debtor accounts → customers/services) from the Sage ledger. */
final class ImportLedger extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $r = app(SageLedgerImportService::class)->run();

        $counts = $r['counts'] ?? [];
        $summary = collect($counts)
            ->map(fn ($v, $k) => Str::headline((string) $k).': '.number_format((float) $v))
            ->implode(' · ');

        return [
            ['counts' => $counts, 'warnings' => $r['warnings'] ?? []],
            trim("Imported into {$r['municipality']}. {$summary}"),
        ];
    }

    protected function title(SageOperation $operation): string
    {
        return 'Sage ledger import';
    }
}
