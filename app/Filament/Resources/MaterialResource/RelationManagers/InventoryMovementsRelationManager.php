<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Enums\MaterialType;
use App\Enums\MovementType;
use App\Models\WarehouseStock;
use App\Services\InventoryMovementService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InventoryMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryMovements';

    protected static ?string $title = 'Historial de movimientos';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'movimientos';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('movement_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Movimiento')
                    ->badge()
                    ->formatStateUsing(
                        fn($state): string =>
                        $state instanceof MovementType
                        ? $state->label()
                        : MovementType::from($state)->label()
                    )
                    ->color(
                        fn($state): string =>
                        $state instanceof MovementType
                        ? $state->color()
                        : MovementType::from($state)->color()
                    ),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Saldo anterior')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo posterior')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Responsable')
                    ->searchable(),

                Tables\Columns\TextColumn::make('observations')
                    ->label('Observaciones')
                    ->limit(40)
                    ->placeholder('Sin observaciones')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de movimiento')
                    ->options(
                        collect(MovementType::cases())
                            ->mapWithKeys(
                                fn(MovementType $type): array => [
                                    $type->value => $type->label(),
                                ]
                            )
                            ->all()
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('entry')
                    ->label('Registrar entrada')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(
                        fn(): bool =>
                        auth()->user()?->can('inventory_movement.entry')
                        ?? false
                    )
                    ->form($this->movementForm())
                    ->action(function (array $data): void {
                        app(InventoryMovementService::class)
                            ->registerEntry(
                                stock: $this->findStock(
                                    $data['warehouse_stock_id']
                                ),
                                quantity: $data['quantity'],
                                observations: $data['observations'] ?? null,
                                movementDate: Carbon::parse(
                                    $data['movement_date']
                                ),
                            );

                        Notification::make()
                            ->title('Entrada registrada correctamente')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('consumption')
                    ->label('Registrar consumo')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->visible(
                        fn(): bool =>
                        $this->getOwnerRecord()->type
                        === MaterialType::CONSUMABLE
                        && (
                            auth()->user()?->can(
                                'inventory_movement.consumption'
                            ) ?? false
                        )
                    )
                    ->form($this->movementForm())
                    ->action(function (array $data): void {
                        app(InventoryMovementService::class)
                            ->registerConsumption(
                                stock: $this->findStock(
                                    $data['warehouse_stock_id']
                                ),
                                quantity: $data['quantity'],
                                observations: $data['observations'] ?? null,
                                movementDate: Carbon::parse(
                                    $data['movement_date']
                                ),
                            );

                        Notification::make()
                            ->title('Consumo registrado correctamente')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('adjustment')
                    ->label('Ajustar inventario')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(
                        fn(): bool =>
                        auth()->user()?->can(
                            'inventory_movement.adjustment'
                        ) ?? false
                    )
                    ->form([
                        Forms\Components\Select::make('direction')
                            ->label('Tipo de ajuste')
                            ->options([
                                'positive' => 'Ajuste positivo',
                                'negative' => 'Ajuste negativo',
                            ])
                            ->native(false)
                            ->required(),

                        ...$this->movementForm(),
                    ])
                    ->action(function (array $data): void {
                        $service = app(InventoryMovementService::class);

                        $parameters = [
                            'stock' => $this->findStock(
                                $data['warehouse_stock_id']
                            ),
                            'quantity' => $data['quantity'],
                            'observations' => $data['observations'] ?? null,
                            'movementDate' => Carbon::parse(
                                $data['movement_date']
                            ),
                        ];

                        if ($data['direction'] === 'positive') {
                            $service->registerPositiveAdjustment(...$parameters);
                        } else {
                            $service->registerNegativeAdjustment(...$parameters);
                        }

                        Notification::make()
                            ->title('Inventario ajustado correctamente')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    private function movementForm(): array
    {
        return [
            Forms\Components\Select::make('warehouse_stock_id')
                ->label('Bodega')
                ->options(fn(): array => $this->stockOptions())
                ->searchable()
                ->native(false)
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->label('Cantidad')
                ->numeric()
                ->minValue(0.001)
                ->step(0.001)
                ->required(),

            Forms\Components\DateTimePicker::make('movement_date')
                ->label('Fecha del movimiento')
                ->default(now())
                ->seconds(false)
                ->required(),

            Forms\Components\Textarea::make('observations')
                ->label('Observaciones')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    private function stockOptions(): array
    {
        return $this->getOwnerRecord()
            ->warehouseStocks()
            ->with('warehouse')
            ->whereHas(
                'warehouse',
                fn($query) => $query->where('is_active', true)
            )
            ->get()
            ->mapWithKeys(
                fn(WarehouseStock $stock): array => [
                    $stock->getKey() => sprintf(
                        '%s - %s (Stock: %.3f)',
                        $stock->warehouse->code,
                        $stock->warehouse->name,
                        (float) $stock->current_stock
                    ),
                ]
            )
            ->all();
    }

    private function findStock(int|string $stockId): WarehouseStock
    {
        return $this->getOwnerRecord()
            ->warehouseStocks()
            ->whereHas(
                'warehouse',
                fn($query) => $query->where('is_active', true)
            )
            ->findOrFail($stockId);
    }

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return auth()->user()?->can('inventory_movement.view') ?? false;
    }
}