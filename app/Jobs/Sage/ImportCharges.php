<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\SageOperation;
use App\Services\Sage\SageChargeImportService;
use RuntimeException;

/**
 * Imports per-client USD charges from the council charge workbook onto customer
 * services. The workbook must be reachable by the worker: `params['path']` is a
 * path on the worker (or a shared disk the worker can read) — file transfer from
 * the cloud UI to the worker is handled by the caller (e.g. a shared storage disk).
 */
final class ImportCharges extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $path = (string) ($operation->params['path'] ?? '');
        if ($path === '') {
            throw new RuntimeException('No workbook path was provided for the charge import.');
        }

        $r = app(SageChargeImportService::class)->run($path);

        $tokens = $r['tokens'] ?? [];
        $clients = collect($tokens)->sum(fn ($t) => (int) ($t['clients'] ?? 0));

        return [
            ['tokens' => $tokens, 'warnings' => $r['warnings'] ?? []],
            "Imported charges for {$clients} client-service row(s) into {$r['municipality']}.",
        ];
    }

    protected function title(SageOperation $operation): string
    {
        return 'Sage charge import';
    }
}
