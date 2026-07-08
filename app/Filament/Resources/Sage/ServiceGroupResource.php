<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage;

use App\Filament\Resources\Sage\Pages\ListServiceGroups;
use App\Models\Sage\ServiceGroup;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceGroupResource extends SageResource
{
    protected static ?string $model = ServiceGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Service Groups';

    protected static ?string $modelLabel = 'service group';

    protected static ?string $recordTitleAttribute = 'Name';

    /** Sage `_ccg_EB_Services.CalculationMethod` codes. */
    public const CALC_METHODS = [
        1 => 'Fixed charge',
        2 => 'Metered / consumption',
        3 => 'Rated',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('Name')
            ->columns([
                TextColumn::make('Name')->searchable()->sortable(),
                TextColumn::make('Description')->limit(50)->toggleable(),
                TextColumn::make('services_count')->label('Services')->counts('services')->badge()->color('primary'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service group')->columns(2)->schema([
                TextEntry::make('Name'),
                TextEntry::make('Description')->placeholder('—'),
            ]),
            Section::make('Services')->schema([
                RepeatableEntry::make('services')
                    ->hiddenLabel()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('Name'),
                        TextEntry::make('CalculationMethod')->label('Calculation')
                            ->formatStateUsing(fn ($state) => self::CALC_METHODS[$state] ?? "#{$state}"),
                        TextEntry::make('MeasurableUnit')->label('Unit')->placeholder('—'),
                        TextEntry::make('tariffs_count')->label('Tariffs')
                            ->state(fn ($record) => $record->tariffs()->count()),
                    ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceGroups::route('/'),
        ];
    }
}
