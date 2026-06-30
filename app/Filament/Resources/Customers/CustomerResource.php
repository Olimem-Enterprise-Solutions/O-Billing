<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Service;
use App\Support\Currencies;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Properties';

    protected static ?string $modelLabel = 'property';

    protected static ?string $pluralModelLabel = 'properties';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('account_number')
                            ->required()
                            ->default(fn () => 'ACC-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT)),
                        TextInput::make('name')->label('Account holder')->required(),
                        Select::make('type')
                            ->options([
                                'residential' => 'Residential',
                                'business' => 'Business',
                                'government' => 'Government',
                            ])
                            ->default('residential')
                            ->required()
                            ->native(false),
                        Select::make('area_id')
                            ->label('Suburb')
                            ->options(fn () => Area::billingLevel()->with('parent')->get()
                                ->mapWithKeys(fn (Area $a) => [$a->id => $a->pathLabel()]))
                            ->searchable()
                            ->required()
                            ->native(false),
                        Select::make('currency')
                            ->options(fn () => collect(Filament::getTenant()->currencies())
                                ->mapWithKeys(fn ($c) => [$c => $c]))
                            ->default(fn () => Filament::getTenant()->base_currency)
                            ->required()
                            ->native(false),
                        TextInput::make('property_value')
                            ->label('Property value')
                            ->numeric()
                            ->helperText('Used for property-rates services.'),
                    ]),
                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')->email(),
                        TextInput::make('phone'),
                        TextInput::make('address')->columnSpanFull(),
                    ]),
                Section::make('Billing')
                    ->schema([
                        Select::make('services')
                            ->label('Subscribed services')
                            ->relationship('services', 'name')
                            ->options(fn () => Service::groupedOptions())
                            ->multiple()
                            ->preload()
                            ->helperText('Only these services are charged on this account. For a service with variants (e.g. Property Rates), pick the one that applies to this property.'),
                        Toggle::make('active')->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_number')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('area.name')
                    ->label('Suburb')
                    ->description(fn (Customer $r) => $r->area?->parent?->pathLabel()),
                TextColumn::make('type')->badge(),
                TextColumn::make('property_value')
                    ->formatStateUsing(fn ($state, Customer $r) => $state ? Currencies::format($state, $r->currency) : '—'),
                TextColumn::make('currency')->badge(),
                TextColumn::make('services_count')->label('Services')->counts('services')->badge()->color('primary'),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('area_id')
                    ->label('Suburb')
                    ->options(fn () => Area::billingLevel()->pluck('name', 'id')),
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'residential' => 'Residential',
                        'business' => 'Business',
                        'government' => 'Government',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomers::route('/'),
        ];
    }
}
