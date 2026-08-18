<?php

namespace App\Filament\Widgets;

use App\Enums\LoanStatus;
use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveLoansTable extends BaseWidget
{
    protected static ?string $heading = 'Préstamos pendientes de devolución';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('loan.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Loan::query()
                    ->whereIn('status', [
                        LoanStatus::ACTIVE->value,
                        LoanStatus::PARTIALLY_RETURNED->value,
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('person.name')
                    ->label('Persona')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items.material.name')
                    ->label('Materiales')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),

                Tables\Columns\TextColumn::make('loan_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_return_date')
                    ->label('Devolución prevista')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin fecha')
                    ->color(
                        fn(Loan $record): string =>
                        $record->isOverdue()
                        ? 'danger'
                        : 'gray'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('loan_date', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(
                        fn(Loan $record): string =>
                        LoanResource::getUrl('view', [
                            'record' => $record,
                        ])
                    ),
            ])
            ->emptyStateHeading('No hay préstamos pendientes')
            ->paginated([5, 10, 25]);
    }
}