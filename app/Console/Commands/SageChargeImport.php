<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sage\SageChargeImportService;
use Illuminate\Console\Command;
use Throwable;

class SageChargeImport extends Command
{
    protected $signature = 'sage:import-charges
        {file : Path to the Billing.Groups2 workbook (.xlsx)}';

    protected $description = 'Import per-client USD charges (licences, lease rent, levies) from the council charge workbook onto customer services';

    public function handle(SageChargeImportService $import): int
    {
        try {
            $result = $import->run((string) $this->argument('file'));
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported per-client charges for {$result['municipality']}.");
        $this->newLine();

        $rows = [];
        foreach ($result['tokens'] as $token => $t) {
            $rows[] = [
                $token,
                $t['sheet'],
                number_format($t['clients']),
                $t['min'] !== null ? number_format((float) $t['min'], 2) : '—',
                $t['max'] !== null ? number_format((float) $t['max'], 2) : '—',
                number_format($t['total'], 2),
                number_format($t['missing']),
            ];
        }
        $this->table(['Token', 'Sheet', 'Clients', 'Min USD', 'Max USD', 'Total USD/period', 'Skipped'], $rows);

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
