<?php

declare(strict_types=1);

namespace App\Filament\Resources\SageOperations;

use App\Filament\Resources\SageOperations\Pages\ListSageOperations;
use App\Models\SageOperation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Read-only monitor for Sage bridge operations (imports, posts, pushes) as they
 * move through the queue and are run by the on-site worker.
 */
class SageOperationResource extends Resource
{
    protected static ?string $model = SageOperation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Sage';

    protected static ?string $navigationLabel = 'Sage Operations';

    protected static ?string $modelLabel = 'Sage operation';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    private const STATUS_COLORS = [
        SageOperation::STATUS_QUEUED => 'gray',
        SageOperation::STATUS_RUNNING => 'warning',
        SageOperation::STATUS_DONE => 'success',
        SageOperation::STATUS_FAILED => 'danger',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s') // statuses update as the worker progresses
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Str::headline($state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->state(fn (SageOperation $r) => $r->subject_type
                        ? class_basename($r->subject_type).' #'.$r->subject_id
                        : '—'),
                TextColumn::make('message')->limit(60)->tooltip(fn (SageOperation $r) => $r->message),
                TextColumn::make('user.name')->label('By')->placeholder('system')->toggleable(),
                TextColumn::make('created_at')->label('Queued')->since()->sortable(),
                TextColumn::make('finished_at')->label('Finished')->since()->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('type')->formatStateUsing(fn (string $state) => Str::headline($state)),
                TextEntry::make('status')->badge()
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('subject')
                    ->state(fn (SageOperation $r) => $r->subject_type
                        ? class_basename($r->subject_type).' #'.$r->subject_id
                        : '—'),
                TextEntry::make('user.name')->label('Triggered by')->placeholder('system'),
                TextEntry::make('started_at')->placeholder('—'),
                TextEntry::make('finished_at')->placeholder('—'),
                TextEntry::make('message')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Result')->collapsed()->schema([
                TextEntry::make('result')->hiddenLabel()
                    ->state(fn (SageOperation $r) => json_encode(
                        $r->result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    )),
            ])->visible(fn (SageOperation $r) => ! empty($r->result)),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSageOperations::route('/'),
        ];
    }
}
