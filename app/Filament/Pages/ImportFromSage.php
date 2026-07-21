<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\Sage\ImportLedger;
use App\Jobs\Sage\ImportProperties;
use App\Jobs\Sage\ImportTariffs;
use App\Models\Area;
use App\Models\BillingRun;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Tariff;
use App\Support\Sage\SageBridge;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Queues master-data imports from the council's Sage database, run by the
 * on-site worker (the cloud app has no Sage connection). Pick the import that
 * matches the council's Sage setup: "ratepayers (ledger)" for debtors-ledger
 * companies (e.g. Gokwe/Binga), or "master data (property module)" for
 * property-module companies. Idempotent — re-run any time to refresh.
 */
class ImportFromSage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Sage';

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
            $this->queueAction(
                'importLedger',
                'Import ratepayers (ledger)',
                'Queues an import of ratepayers (debtor accounts → customers and services) from the Sage debtor ledger. For debtors-ledger councils (e.g. Gokwe/Binga).',
                'import_ledger',
                ImportLedger::class,
            ),
            $this->queueAction(
                'importProperties',
                'Import master data (property module)',
                'Queues an import of areas, properties, customers, services, tariffs and billing runs from the Sage property module. For property-module councils.',
                'import_properties',
                ImportProperties::class,
            ),
            $this->queueAction(
                'importTariffs',
                'Price tariffs',
                'Queues pricing of the recurring services (assessment rates, development levies) and attaches a ward tariff to every affected property. Run after a ledger import.',
                'import_tariffs',
                ImportTariffs::class,
            )->icon(Heroicon::OutlinedCurrencyDollar),
        ];
    }

    private function queueAction(string $name, string $label, string $description, string $type, string $job): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription($description)
            ->modalSubmitActionLabel('Queue import')
            ->action(function () use ($type, $job): void {
                SageBridge::queue($type, $job);

                Notification::make()
                    ->success()
                    ->title('Import queued')
                    ->body('The import has been queued and will run on the on-site worker. Watch progress under Sage → Sage Operations.')
                    ->send();
            });
    }
}
