<?php

namespace App\Filament\Resources;

use App\Enums\LoanStatus;
use App\Enums\MaterialType;
use App\Filament\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\Material;
use App\Models\Person;
use App\Models\WarehouseStock;
use App\Services\LoanReturnService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use App\Models\LoanItem;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Préstamos';

    protected static ?string $modelLabel = 'préstamo';

    protected static ?string $pluralModelLabel = 'préstamos';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del préstamo')
                    ->schema([
                        Forms\Components\Select::make('person_id')
                            ->label('Persona que recibe')
                            ->relationship(
                                name: 'person',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) =>
                                $query->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre completo')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                abort_unless(
                                    auth()->user()?->can('person.create'),
                                    403
                                );

                                return Person::query()->create([
                                    'name' => trim($data['name']),
                                    'is_active' => true,
                                ])->getKey();
                            })
                            ->createOptionAction(
                                fn(Forms\Components\Actions\Action $action) =>
                                $action
                                    ->label('Registrar nueva persona')
                                    ->modalHeading('Registrar nueva persona')
                                    ->modalSubmitActionLabel('Registrar')
                                    ->visible(
                                        fn(): bool =>
                                        auth()->user()?->can('person.create') ?? false
                                    )
                            )
                            ->required(),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Bodega')
                            ->relationship(
                                name: 'warehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) =>
                                $query->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('items', [
                                    [
                                        'material_id' => null,
                                        'quantity' => 1,
                                    ],
                                ]);
                            })
                            ->required(),

                        Forms\Components\DateTimePicker::make('loan_date')
                            ->label('Fecha de entrega')
                            ->default(now())
                            ->seconds(false)
                            ->live()
                            ->required(),

                        Forms\Components\DatePicker::make(
                            'expected_return_date'
                        )
                            ->label('Fecha esperada de devolución')
                            ->minDate(
                                fn(Get $get) => filled($get('loan_date'))
                                ? Carbon::parse(
                                    $get('loan_date')
                                )->startOfDay()
                                : today()
                            )
                            ->native(false),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Materiales entregados')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            // No debe llevar ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label('Material')
                                    ->options(function (Get $get): array {
                                        $warehouseId = $get('../../warehouse_id');
                                        return self::materialOptions($warehouseId);
                                    })
                                    ->disabled(
                                        fn(Get $get): bool => blank($get('../../warehouse_id'))
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->minItems(1)
                            ->required()
                            ->columns(2)
                            ->default([
                                [
                                    'material_id' => '',
                                    'quantity' => 1,
                                ],
                            ])
                            ->minItems(1)
                            ->addActionLabel('Agregar otro material')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Firma de recepción')
                    ->description(
                        'La firma respalda todos los materiales incluidos.'
                    )
                    ->schema([
                        SignaturePad::make('signature')
                            ->label('Firma de quien recibe')
                            ->backgroundColor('#ffffff')
                            ->backgroundColorOnDark('#ffffff')
                            ->exportBackgroundColor('#ffffff')
                            ->penColor('#111827')
                            ->penColorOnDark('#111827')
                            ->exportPenColor('#111827')
                            ->confirmable()
                            ->undoable()
                            ->clearable()
                            ->downloadable(false)
                            ->required()
                            ->helperText(
                                'La persona debe presionar el botón de confirmación después de firmar.'
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('loan_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('person.name')
                    ->label('Persona')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('loan_date')
                    ->label('Fecha de entrega')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_return_date')
                    ->label('Devolución esperada')
                    ->date('d/m/Y')
                    ->placeholder('Sin fecha')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Materiales')
                    ->counts('items')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn($state, Loan $record): string =>
                        $record->isOverdue()
                        ? 'Vencido'
                        : (
                            $state instanceof LoanStatus
                            ? $state->label()
                            : LoanStatus::from($state)->label()
                        )
                    )
                    ->color(
                        fn($state, Loan $record): string =>
                        $record->isOverdue()
                        ? 'danger'
                        : (
                            $state instanceof LoanStatus
                            ? $state->color()
                            : LoanStatus::from($state)->color()
                        )
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        LoanStatus::ACTIVE->value =>
                            LoanStatus::ACTIVE->label(),

                        LoanStatus::PARTIALLY_RETURNED->value =>
                            LoanStatus::PARTIALLY_RETURNED->label(),

                        LoanStatus::RETURNED->value =>
                            LoanStatus::RETURNED->label(),
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidos')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query
                            ->where(
                                'status',
                                '!=',
                                LoanStatus::RETURNED->value
                            )
                            ->whereNotNull('expected_return_date')
                            ->whereDate(
                                'expected_return_date',
                                '<',
                                today()
                            )
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('registerReturn')
                    ->label('Registrar devolución')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(
                        fn(Loan $record): bool =>
                        auth()->user()?->can('loan.return')
                        && $record->status !== LoanStatus::RETURNED
                    )
                    ->modalHeading(
                        fn(Loan $record): string =>
                        "Devolución del préstamo {$record->code}"
                    )
                    ->modalSubmitActionLabel('Registrar devolución')
                    ->form([
                        DateTimePicker::make('return_date')
                            ->label('Fecha de devolución')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->required(),

                        Repeater::make('items')
                            ->label('Materiales pendientes')
                            ->schema([
                                Hidden::make('loan_item_id'),

                                Hidden::make('material_label'),

                                Hidden::make('pending_quantity'),

                                Toggle::make('selected')
                                    ->label('Devolver')
                                    ->columnSpan(2)
                                    ->live(),

                                Placeholder::make('material')
                                    ->label('Material')
                                    ->columnSpan(4)
                                    ->content(
                                        fn(Get $get): string =>
                                        $get('material_label') ?? '-'
                                    ),

                                TextInput::make('quantity')
                                    ->label('Cantidad devuelta')
                                    ->columnSpan(2)
                                    ->numeric()
                                    ->step(0.001)
                                    ->minValue(0.001)
                                    ->maxValue(
                                        fn(Get $get): float =>
                                        (float) $get('pending_quantity')
                                    )
                                    ->required(
                                        fn(Get $get): bool =>
                                        (bool) $get('selected')
                                    )
                                    ->visible(
                                        fn(Get $get): bool =>
                                        (bool) $get('selected')
                                    ),
                            ])
                            ->columns(8)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->mountUsing(function (Form $form, Loan $record): void {
                        $items = $record->items()
                            ->with('material')
                            ->whereColumn('returned_quantity', '<', 'quantity')
                            ->get()
                            ->map(function ($item): array {
                                $pending = round(
                                    (float) $item->quantity
                                    - (float) $item->returned_quantity,
                                    3
                                );

                                return [
                                    'loan_item_id' => $item->getKey(),
                                    'material_label' => sprintf(
                                        '%s - %s (Pendiente: %.3f)',
                                        $item->material->code,
                                        $item->material->name,
                                        $pending
                                    ),
                                    'pending_quantity' => $pending,
                                    'selected' => true,
                                    'quantity' => $pending,
                                ];
                            })
                            ->all();

                        $form->fill([
                            'return_date' => now(),
                            'items' => $items,
                            'observations' => null,
                        ]);
                    })
                    ->action(function (Loan $record, array $data): void {
                        $data['items'] = collect($data['items'])
                            ->filter(
                                fn(array $item): bool =>
                                (bool) ($item['selected'] ?? false)
                            )
                            ->map(fn(array $item): array => [
                                'loan_item_id' => $item['loan_item_id'],
                                'quantity' => $item['quantity'],
                            ])
                            ->values()
                            ->all();

                        if ($data['items'] === []) {
                            throw ValidationException::withMessages([
                                'items' => 'Selecciona al menos un material.',
                            ]);
                        }

                        app(LoanReturnService::class)->create(
                            loan: $record,
                            data: $data,
                        );

                        Notification::make()
                            ->title('Devolución registrada correctamente')
                            ->success()
                            ->send();
                    })
            ])
            ->bulkActions([]);
    }

    private static function materialOptions(int|string|null $warehouseId): array
    {
        if (blank($warehouseId)) {
            return [];
        }

        return Material::query()
            ->where('is_active', true)
            ->whereHas(
                'warehouseStocks',
                fn(Builder $query): Builder => $query
                    ->where('warehouse_id', $warehouseId)
                    ->where('current_stock', '>', 0)
            )
            ->with([
                'warehouseStocks' => fn(Builder $query): Builder =>
                    $query->where('warehouse_id', $warehouseId),
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Material $material): array {
                $stock = $material->warehouseStocks->first();

                return [
                    $material->getKey() => sprintf(
                        '%s - %s (Disponible: %.3f)',
                        $material->code,
                        $material->name,
                        (float) ($stock?->current_stock ?? 0)
                    ),
                ];
            })
            ->all();
    }

    private static function availableStock(Get $get): ?float
    {
        $warehouseId = $get('../../warehouse_id');
        $materialId = $get('material_id');

        if (blank($warehouseId) || blank($materialId)) {
            return null;
        }

        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('material_id', $materialId)
            ->value('current_stock');

        return $stock !== null ? (float) $stock : null;
    }

    private static function stockHint(Get $get): ?string
    {
        $stock = self::availableStock($get);

        return $stock !== null
            ? 'Disponible: ' . number_format($stock, 3)
            : null;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('loan.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('loan.create') ?? false;
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
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),

        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del préstamo')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código')
                            ->copyable(),

                        TextEntry::make('person.name')
                            ->label('Persona'),

                        TextEntry::make('warehouse.name')
                            ->label('Bodega'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),

                        TextEntry::make('loan_date')
                            ->label('Fecha del préstamo')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('expected_return_date')
                            ->label('Devolución prevista')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sin fecha prevista'),

                        TextEntry::make('overdue')
                            ->label('Vencido')
                            ->state(
                                fn(Loan $record): string =>
                                $record->isOverdue() ? 'Sí' : 'No'
                            )
                            ->badge()
                            ->color(
                                fn(Loan $record): string =>
                                $record->isOverdue()
                                ? 'danger'
                                : 'success'
                            ),

                        TextEntry::make('creator.name')
                            ->label('Registrado por'),

                        TextEntry::make('observations')
                            ->label('Observaciones')
                            ->placeholder('Sin observaciones')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Section::make('Materiales entregados')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('material.code')
                                    ->label('Código'),

                                TextEntry::make('material.name')
                                    ->label('Material'),

                                TextEntry::make('quantity')
                                    ->label('Entregado')
                                    ->formatStateUsing(
                                        fn($state): string =>
                                        number_format((float) $state, 3)
                                    ),

                                TextEntry::make('returned_quantity')
                                    ->label('Devuelto')
                                    ->formatStateUsing(
                                        fn($state): string =>
                                        number_format((float) $state, 3)
                                    ),

                                TextEntry::make('pending_quantity')
                                    ->label('Pendiente')
                                    ->state(
                                        fn(LoanItem $record): string =>
                                        number_format(
                                            max(
                                                0,
                                                (float) $record->quantity
                                                - (float) $record->returned_quantity
                                            ),
                                            3
                                        )
                                    ),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Firma de recepción')
                    ->schema([
                        ImageEntry::make('signature_path')
                            ->label('Firma')
                            ->state(
                                fn(Loan $record): string =>
                                route('loans.signature', $record)
                            )
                            ->height(180)
                            ->checkFileExistence(false)
                            ->url(
                                fn(Loan $record): string =>
                                route('loans.signature', $record)
                            )
                            ->openUrlInNewTab(),
                    ])
                    ->visible(
                        fn(Loan $record): bool =>
                        filled($record->signature_path)
                    ),

                Section::make('Historial de devoluciones')
                    ->schema([
                        RepeatableEntry::make('loanReturns')
                            ->label('')
                            ->schema([
                                TextEntry::make('return_date')
                                    ->label('Fecha')
                                    ->dateTime('d/m/Y H:i'),

                                TextEntry::make('receivedBy.name')
                                    ->label('Recibido por'),

                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('Sin observaciones'),

                                RepeatableEntry::make('items')
                                    ->label('Materiales devueltos')
                                    ->schema([
                                        TextEntry::make(
                                            'loanItem.material.code'
                                        )
                                            ->label('Código'),

                                        TextEntry::make(
                                            'loanItem.material.name'
                                        )
                                            ->label('Material'),

                                        TextEntry::make('quantity')
                                            ->label('Cantidad')
                                            ->formatStateUsing(
                                                fn($state): string =>
                                                number_format(
                                                    (float) $state,
                                                    3
                                                )
                                            ),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                    ])
                    ->visible(
                        fn(Loan $record): bool =>
                        $record->loanReturns()->exists()
                    ),
            ]);
    }
}