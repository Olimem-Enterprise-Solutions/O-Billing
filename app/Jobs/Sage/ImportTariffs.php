<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\SageOperation;
use App\Services\Sage\SageTariffImportService;

/** Prices recurring services (assessment rates, development levies) and attaches ward tariffs. */
final class ImportTariffs extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $r = app(SageTariffImportService::class)->run();

        $counts = $r['counts'] ?? [];
        $priced = (int) ($counts['services_priced'] ?? 0);
        $tariffs = (int) ($counts['tariffs_created'] ?? 0);

        return [
            ['counts' => $counts, 'lines' => $r['lines'] ?? [], 'warnings' => $r['warnings'] ?? []],
            "Priced {$priced} service(s) and created {$tariffs} tariff(s) for {$r['municipality']}.",
        ];
    }

    protected function title(SageOperation $operation): string
    {
        return 'Sage tariff pricing';
    }
}
