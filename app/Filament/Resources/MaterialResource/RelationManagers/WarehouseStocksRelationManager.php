<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Models\WarehouseStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class WarehouseStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouseStocks';

    protected static ?string $title = 'Existencias por bodega';

    protected static ?string $modelLabel = 'existencia';

    protected static ?string $pluralModelLabel = 'existencias';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship(
                        name: 'warehouse',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn($query) =>
                        $query->where('is_active', true)
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn($record): string =>
                        "{$record->code} - {$record->name}"
                    )
                    ->searchable(['code', 'name'])
                    ->preload()
                    ->required()
                    ->unique(
                        table: WarehouseStock::class,
                        column: 'warehouse_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn(Unique $rule): Unique =>
                        $rule->where(
                            'material_id',
                            $this->getOwnerRecord()->getKey()
                        )
                    )
                    ->disabledOn('edit'),

                Forms\Components\TextInput::make('current_stock')
                    ->label('Existencia actual')
                    ->default(0)
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(
                        'La existencia solamente se modifica mediante movimientos.'
                    ),

                Forms\Components\TextInput::make('minimum_stock')
                    ->label('Stock mínimo')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->nullable()
                    ->helperText(
                        'Déjalo vacío si no deseas recibir alertas de stock bajo.'
                    ),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.code')
                    ->label('Código')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Existencia')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Stock mínimo')
                    ->formatStateUsing(
                        fn($state): string =>
                        number_format((float) $state, 3)
                    )
                    ->placeholder('Sin límite')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_low_stock')
                    ->label('Stock bajo')
                    ->state(
                        fn(WarehouseStock $record): bool =>
                        $record->isLowStock()
                    )
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Asignar bodega')
                    ->visible(
                        fn(): bool =>
                        auth()->user()?->can('warehouse_stock.create')
                        ?? false
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Configurar mínimo')
                    ->visible(
                        fn(): bool =>
                        auth()->user()?->can('warehouse_stock.update')
                        ?? false
                    ),

                Tables\Actions\DeleteAction::make()
                    ->label('Desasignar')
                    ->visible(
                        fn(WarehouseStock $record): bool =>
                        (auth()->user()?->can('warehouse_stock.delete')
                            ?? false)
                        && (float) $record->current_stock === 0.0
                    ),
            ])
            ->bulkActions([]);
    }

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        return auth()->user()?->can('warehouse_stock.view') ?? false;
    }
}