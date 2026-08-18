<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseStockResource\Pages;
use App\Filament\Resources\WarehouseStockResource\RelationManagers;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\InventoryTransferService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\MaterialType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

class WarehouseStockResource extends Resource
{
    protected static ?string $model = WarehouseStock::class;

    protected static ?string $navigationIcon =
        'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Existencias';

    protected static ?string $modelLabel = 'existencia';

    protected static ?string $pluralModelLabel = 'existencias';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Existencia')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Bodega')
                            ->relationship('warehouse', 'name')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('material_id')
                            ->label('Material')
                            ->relationship('material', 'name')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('current_stock')
                            ->label('Existencia actual')
                            ->formatStateUsing(
                                fn($state): string =>
                                number_format((float) $state, 3)
                            )
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('minimum_stock')
                            ->label('Stock mínimo')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0)
                            ->nullable()
                            ->helperText(
                                'Déjalo vacío para desactivar la alerta.'
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('material.code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.category.name')
                    ->label('Categoría')
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.type')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Existencia')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->color(
                        fn(WarehouseStock $record): string =>
                        $record->isLowStock()
                        ? 'danger'
                        : 'success'
                    )
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Mínimo')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->placeholder('No configurado')
                    ->sortable(),

                Tables\Columns\IconColumn::make('low_stock')
                    ->label('Stock bajo')
                    ->state(
                        fn(WarehouseStock $record): bool =>
                        $record->isLowStock()
                    )
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->options(
                        Category::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        $query->when(
                            $data['value'],
                            fn(Builder $query, $categoryId) =>
                            $query->whereHas(
                                'material',
                                fn(Builder $materialQuery) =>
                                $materialQuery->where(
                                    'category_id',
                                    $categoryId
                                )
                            )
                        )
                    ),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(MaterialType::options())
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        $query->when(
                            $data['value'],
                            fn(Builder $query, $type) =>
                            $query->whereHas(
                                'material',
                                fn(Builder $materialQuery) =>
                                $materialQuery->where(
                                    'type',
                                    $type
                                )
                            )
                        )
                    ),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Solo stock bajo')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query
                            ->whereNotNull('minimum_stock')
                            ->whereColumn(
                                'current_stock',
                                '<=',
                                'minimum_stock'
                            )
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('transfer')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(
                        fn(WarehouseStock $record): bool =>
                        auth()->user()?->can('inventory_transfer.create')
                        && (float) $record->current_stock > 0
                        && $record->material?->is_active
                        && $record->warehouse?->is_active
                    )
                    ->modalHeading(
                        fn(WarehouseStock $record): string =>
                        "Transferir {$record->material->name}"
                    )
                    ->modalDescription(
                        fn(WarehouseStock $record): string => sprintf(
                            'Origen: %s. Existencia disponible: %.3f.',
                            $record->warehouse->name,
                            (float) $record->current_stock
                        )
                    )
                    ->modalSubmitActionLabel('Registrar transferencia')
                    ->form([
                        Forms\Components\Select::make(
                            'destination_warehouse_id'
                        )
                            ->label('Bodega destino')
                            ->options(
                                fn(WarehouseStock $record): array =>
                                Warehouse::query()
                                    ->where('is_active', true)
                                    ->whereKeyNot($record->warehouse_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0.001)
                            ->maxValue(
                                fn(WarehouseStock $record): float =>
                                (float) $record->current_stock
                            )
                            ->required(),

                        Forms\Components\DateTimePicker::make('transfer_date')
                            ->label('Fecha de transferencia')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->required(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->action(
                        function (WarehouseStock $record, array $data): void {
                            $transfer = app(
                                InventoryTransferService::class
                            )->create(
                                    sourceStock: $record,
                                    data: $data,
                                );

                            Notification::make()
                                ->title('Transferencia registrada')
                                ->body(sprintf(
                                    '%s: %.3f unidades transferidas.',
                                    $transfer->code,
                                    (float) $transfer->quantity
                                ))
                                ->success()
                                ->send();
                        }
                    ),
                Tables\Actions\EditAction::make()
                    ->label('Configurar mínimo'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseStocks::route('/'),
            'edit' => Pages\EditWarehouseStock::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('warehouse_stock.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('warehouse_stock.update') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
