<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryTransferResource\Pages;
use App\Models\InventoryTransfer;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationIcon =
        'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Transferencias';

    protected static ?string $modelLabel = 'transferencia';

    protected static ?string $pluralModelLabel = 'transferencias';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transfer_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.code')
                    ->label('Código material')
                    ->searchable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'sourceWarehouse.name'
                )
                    ->label('Origen')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'destinationWarehouse.name'
                )
                    ->label('Destino')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Registrado por'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make(
                    'source_warehouse_id'
                )
                    ->label('Bodega origen')
                    ->relationship('sourceWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make(
                    'destination_warehouse_id'
                )
                    ->label('Bodega destino')
                    ->relationship('destinationWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('material_id')
                    ->label('Material')
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('transfer_date')
                    ->label('Rango de fechas')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make(
                            'from'
                        )->label('Desde'),

                        \Filament\Forms\Components\DatePicker::make(
                            'until'
                        )->label('Hasta'),
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date) =>
                                $query->whereDate(
                                    'transfer_date',
                                    '>=',
                                    $date
                                )
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date) =>
                                $query->whereDate(
                                    'transfer_date',
                                    '<=',
                                    $date
                                )
                            )
                    ),
            ])
            ->defaultSort('transfer_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('inventory_transfer.view') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('inventory_transfer.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransfers::route('/'),
            'view' => Pages\ViewInventoryTransfer::route('/{record}'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Transferencia')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código')
                            ->copyable(),

                        TextEntry::make('transfer_date')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('material.code')
                            ->label('Código del material'),

                        TextEntry::make('material.name')
                            ->label('Material'),

                        TextEntry::make('sourceWarehouse.name')
                            ->label('Bodega origen'),

                        TextEntry::make(
                            'destinationWarehouse.name'
                        )
                            ->label('Bodega destino'),

                        TextEntry::make('quantity')
                            ->label('Cantidad transferida')
                            ->formatStateUsing(
                                fn($state): string =>
                                number_format((float) $state, 3)
                            ),

                        TextEntry::make('createdBy.name')
                            ->label('Registrado por'),

                        TextEntry::make('observations')
                            ->label('Observaciones')
                            ->placeholder('Sin observaciones')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Section::make('Movimientos generados')
                    ->schema([
                        RepeatableEntry::make('movements')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge(),

                                TextEntry::make('warehouse.name')
                                    ->label('Bodega'),

                                TextEntry::make('quantity')
                                    ->label('Cantidad')
                                    ->formatStateUsing(
                                        fn($state): string =>
                                        number_format(
                                            (float) $state,
                                            3
                                        )
                                    ),

                                TextEntry::make('balance_before')
                                    ->label('Stock anterior')
                                    ->formatStateUsing(
                                        fn($state): string =>
                                        number_format(
                                            (float) $state,
                                            3
                                        )
                                    ),

                                TextEntry::make('balance_after')
                                    ->label('Stock posterior')
                                    ->formatStateUsing(
                                        fn($state): string =>
                                        number_format(
                                            (float) $state,
                                            3
                                        )
                                    ),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'material',
                'sourceWarehouse',
                'destinationWarehouse',
                'createdBy',
            ]);
    }
}
