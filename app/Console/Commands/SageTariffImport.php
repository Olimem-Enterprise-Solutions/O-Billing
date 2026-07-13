<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sage\SageTariffImportService;
use Illuminate\Console\Command;
use Throwable;

class SageTariffImport extends Command
{
    protected $signature = 'sage:import-tariffs';

    protected $description = 'Price the recurring ratepayer services (Assessment Rates, Development Levy) and attach tariffs to every affected property';

    public function handle(SageTariffImportService $service): int
    {
        $this->info('Pricing recurring services from Sage ('.config('database.connections.sage.database').') …');

        try {
            $result = $service->run();
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Priced into: {$result['municipality']}");
        $this->table(
            ['Service', 'Rate (USD)', 'Frequency', 'Properties', 'Wards', 'Price source'],
            collect($result['lines'])->map(fn ($l) => [
                $l['service'].' ('.$l['token'].')',
                number_format($l['rate'], 2),
                $l['frequency'],
                number_format($l['properties']),
                $l['wards'],
                $l['price_source'],
            ])->all(),
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn('• '.$warning);
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
