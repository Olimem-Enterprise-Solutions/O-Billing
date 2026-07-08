<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sage\SageImportService;
use Illuminate\Console\Command;
use Throwable;

class SageImport extends Command
{
    protected $signature = 'sage:import';

    protected $description = 'Import master data (areas, properties, customers, services, tariffs, billing runs) from the Sage Evolution database';

    public function handle(SageImportService $service): int
    {
        $this->info('Importing from Sage ('.config('database.connections.sage.database').') …');

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
