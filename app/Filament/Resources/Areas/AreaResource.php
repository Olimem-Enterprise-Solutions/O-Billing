<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Models\Area;
use App\Models\AreaType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Area Setup';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('area_type_id')
                    ->label('Level')
                    ->options(fn () => AreaType::orderBy('level')->pluck('name', 'id'))
                    ->required()
                    ->native(false),
                Select::make('parent_id')
                    ->label('Within (parent area)')
                    ->helperText('Leave empty for a top-level area (e.g. a province).')
                    ->options(fn () => Area::with('parent')->get()->mapWithKeys(
                        fn (Area $a) => [$a->id => $a->pathLabel()]
                    ))
                    ->searchable()
                    ->native(false),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')
                    ->description(fn (Area $r) => $r->parent?->pathLabel())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('areaType.name')
                    ->label('Level')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('children_count')
                    ->label('Sub-areas')
                    ->counts('children')
                    ->badge(),
                TextColumn::make('customers_count')
                    ->label('Properties')
                    ->counts('customers')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('area_type_id')
                    ->label('Level')
                    ->options(fn () => AreaType::orderBy('level')->pluck('name', 'id')),
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
            'index' => ManageAreas::route('/'),
        ];
    }
}
