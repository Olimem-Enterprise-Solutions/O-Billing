<?php

namespace App\Filament\Resources\BillingRuns\Pages;

use App\Filament\Resources\BillingRuns\BillingRunResource;
use App\Filament\Resources\BillingSchedules\BillingScheduleResource;
use App\Filament\Resources\BillingSchedules\Schemas\BillingScheduleForm;
use App\Models\BillingSchedule;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBillingRuns extends ListRecords
{
    protected static string $resource = BillingRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Create a recurring schedule right here — it auto-generates (and
            // optionally posts) billing runs on its cadence. Kept as a separate
            // record type (a schedule is a repeating config, not a single run),
            // so this only shares the form, not the runs table.
            Action::make('newSchedule')
                ->label('New schedule')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->modalHeading('New billing schedule')
                ->modalSubmitActionLabel('Create schedule')
                ->schema(BillingScheduleForm::components())
                ->action(function (array $data): void {
                    $schedule = BillingSchedule::create($data);

                    Notification::make()
                        ->success()
                        ->title("Schedule '{$schedule->name}' created")
                        ->body($schedule->next_run_at
                            ? 'Next run: '.$schedule->next_run_at->format('d M Y').'.'
                            : 'Scheduled.')
                        ->send();
                }),

            Action::make('manageSchedules')
                ->label('Billing schedules')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(fn (): string => BillingScheduleResource::getUrl('index')),
        ];
    }
}
