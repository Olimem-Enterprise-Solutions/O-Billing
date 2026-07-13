<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sage\SageLedgerImportService;
use Illuminate\Console\Command;
use Throwable;

class SageLedgerImport extends Command
{
    protected $signature = 'sage:import-ledger';

    protected $description = 'Import ratepayers (properties/stands and their services) from the Sage debtor ledger';

    public function handle(SageLedgerImportService $service): int
    {
        $this->info('Importing ratepayers from the Sage ledger ('.config('database.connections.sage.database').') …');

        try {
            $result = $service->run();
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Imported into: {$result['municipality']}");
        $this->table(
            ['Entity', 'Count'],
            collect($result['counts'])->map(fn ($v, $k) => [str($k)->headline()->toString(), number_format($v)])->values()->all(),
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn('• '.$warning);
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
