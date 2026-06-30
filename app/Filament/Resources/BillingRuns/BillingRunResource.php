<?php

namespace App\Filament\Resources\BillingRuns;

use App\Filament\Resources\BillingRuns\Pages\CreateBillingRun;
use App\Filament\Resources\BillingRuns\Pages\EditBillingRun;
use App\Filament\Resources\BillingRuns\Pages\ListBillingRuns;
use App\Filament\Resources\BillingRuns\Pages\ViewBillingRun;
use App\Filament\Resources\BillingRuns\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\BillingRuns\Schemas\BillingRunForm;
use App\Filament\Resources\BillingRuns\Tables\BillingRunsTable;
use App\Models\BillingRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BillingRunResource extends Resource
{
    protected static ?string $model = BillingRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Billing runs';

    public static function form(Schema $schema): Schema
    {
        return BillingRunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingRuns::route('/'),
            'create' => CreateBillingRun::route('/create'),
            'view' => ViewBillingRun::route('/{record}'),
            'edit' => EditBillingRun::route('/{record}/edit'),
        ];
    }
}
