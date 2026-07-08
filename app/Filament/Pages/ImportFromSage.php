<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Tariff;
use App\Services\Sage\SageImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * One-click importer: pulls the master data from the live Sage Evolution
 * database into O-Billing's own tables (areas, properties, customers, services,
 * tariffs, billing runs) so it appears in the normal Billing screens. Idempotent
 * — re-run any time to refresh.
 */
class ImportFromSage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Sage (Live)';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Import from Sage';

    protected static ?string $title = 'Import from Sage';

    protected string $view = 'filament.pages.import-from-sage';

    /** Current counts of imported data, for the summary panel. */
    public function stats(): array
    {
        return [
            'Areas' => Area::count(),
            'Customers / properties' => Customer::count(),
            'Service types' => ServiceType::count(),
            'Services' => Service::count(),
            'Tariffs' => Tariff::count(),
            'Billing runs' => BillingRun::count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Run import now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Import from Sage')
                ->modalDescription('Reads the live Sage database and refreshes O-Billing\'s areas, properties, customers, services, tariffs and billing runs. This can take a minute for a large database.')
                ->modalSubmitActionLabel('Import')
                ->action(function (SageImportService $service): void {
                    try {
                        $result = $service->run();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        return;
                    }

                    $summary = collect($result['counts'])
                        ->map(fn ($v, $k) => (string) str($k)->headline().': '.number_format($v))
                        ->implode(' · ');

                    Notification::make()
                        ->success()
                        ->title("Imported into {$result['municipality']}")
                        ->body($summary)
                        ->persistent()
                        ->send();

                    foreach ($result['warnings'] as $warning) {
                        Notification::make()->warning()->title('Note')->body($warning)->persistent()->send();
                    }
                }),
        ];
    }
}
