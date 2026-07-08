<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sage;

use App\Filament\Resources\Sage\Pages\ListProperties;
use App\Models\Sage\Property;
use App\Support\Currencies;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PropertyResource extends SageResource
{
    protected static ?string $model = Property::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Properties';

    protected static ?string $modelLabel = 'property';

    protected static ?string $recordTitleAttribute = 'ErfNumber';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['area', 'owner', 'ratingCategory']))
            ->defaultSort('ErfNumber')
            ->columns([
                TextColumn::make('ErfNumber')->label('Erf / stand')->searchable()->sortable(),
                TextColumn::make('owner.Name')->label('Owner')->searchable()->limit(40),
                TextColumn::make('area.Description')->label('Area')->sortable(),
                TextColumn::make('ratingCategory.Name')->label('Category')->toggleable(),
                TextColumn::make('MarketValue')->label('Market value')->sortable()
                    ->formatStateUsing(fn ($state) => $state ? Currencies::format($state, 'USD') : '—')
                    ->alignEnd(),
                TextColumn::make('services_count')->label('Services')->counts('services')->badge()->color('primary'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Property')->columns(3)->schema([
                TextEntry::make('ErfNumber')->label('Erf / stand'),
                TextEntry::make('SGCode')->label('SG code')->placeholder('—'),
                TextEntry::make('DeedNumber')->label('Deed number')->placeholder('—'),
                TextEntry::make('area.Description')->label('Area'),
                TextEntry::make('ratingCategory.Name')->label('Rating category')->placeholder('—'),
                TextEntry::make('Households')->placeholder('—'),
                TextEntry::make('address')->label('Address')->columnSpanFull()
                    ->state(fn (Property $r) => $r->addressLabel() ?: '—'),
            ]),
            Section::make('Valuation')->columns(4)->schema([
                TextEntry::make('MarketValue')->formatStateUsing(fn ($state) => Currencies::format($state, 'USD')),
                TextEntry::make('LandValue')->formatStateUsing(fn ($state) => Currencies::format($state, 'USD')),
                TextEntry::make('ImprovementValue')->formatStateUsing(fn ($state) => Currencies::format($state, 'USD')),
                TextEntry::make('LandSize')->formatStateUsing(fn ($state) => number_format((float) $state, 0).' m²'),
            ]),
            Section::make('Owner')->columns(3)->schema([
                TextEntry::make('owner.Account')->label('Account'),
                TextEntry::make('owner.Name')->label('Name'),
                TextEntry::make('owner.Telephone')->label('Phone')->placeholder('—'),
                TextEntry::make('owner.EMail')->label('E-mail')->placeholder('—'),
                TextEntry::make('owner.DCBalance')->label('Balance')
                    ->formatStateUsing(fn ($state) => Currencies::format($state, 'USD')),
            ]),
            Section::make('Billed services')->schema([
                RepeatableEntry::make('services')
                    ->hiddenLabel()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('service.Name')->label('Service'),
                        TextEntry::make('tariff.Name')->label('Tariff')->placeholder('—'),
                        TextEntry::make('Billable')->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                    ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
        ];
    }
}
