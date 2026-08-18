<?php

namespace App\Filament\Widgets;

use App\Enums\LoanStatus;
use App\Filament\Resources\LoanResource;
use App\Filament\Resources\MaterialResource;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\WarehouseStock;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Filament\Resources\PersonResource;
use App\Models\Material;
use App\Models\Person;

class InventoryStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->can('loan.view')
            && $user?->can('warehouse_stock.view');
    }

    protected function getStats(): array
    {
        $pendingStatuses = [
            LoanStatus::ACTIVE->value,
            LoanStatus::PARTIALLY_RETURNED->value,
        ];

        $pendingLoans = Loan::query()
            ->whereIn('status', $pendingStatuses)
            ->count();

        $overdueLoans = Loan::query()
            ->whereIn('status', $pendingStatuses)
            ->whereNotNull('expected_return_date')
            ->where('expected_return_date', '<', now())
            ->count();

        $lowStock = WarehouseStock::query()
            ->whereNotNull('minimum_stock')
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->whereHas(
                'material',
                fn($query) => $query->where('is_active', true)
            )
            ->whereHas(
                'warehouse',
                fn($query) => $query->where('is_active', true)
            )
            ->count();

        $todayMovements = InventoryMovement::query()
            ->whereDate('movement_date', today())
            ->count();
        $totalMaterials = Material::query()
            ->where('is_active', true)
            ->count();

        $peopleWithPendingLoans = Person::query()
            ->whereHas(
                'loans',
                fn($query) => $query->whereIn(
                    'status',
                    $pendingStatuses
                )
            )
            ->count();
        return [
            Stat::make('Préstamos pendientes', $pendingLoans)
                ->description('Activos o parcialmente devueltos')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color($pendingLoans > 0 ? 'warning' : 'success')
                ->url(LoanResource::getUrl('index')),

            Stat::make('Préstamos vencidos', $overdueLoans)
                ->description('Fuera de la fecha prevista')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueLoans > 0 ? 'danger' : 'success')
                ->url(LoanResource::getUrl('index')),

            Stat::make('Materiales con stock bajo', $lowStock)
                ->description('Existencia igual o inferior al mínimo')
                ->descriptionIcon(
                    'heroicon-m-exclamation-triangle'
                )
                ->color($lowStock > 0 ? 'danger' : 'success')
                ->url(MaterialResource::getUrl('index')),

            Stat::make('Movimientos de hoy', $todayMovements)
                ->description('Operaciones registradas hoy')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),

            Stat::make('Materiales activos', $totalMaterials)
                ->description('Materiales registrados y habilitados')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->url(MaterialResource::getUrl('index')),

            Stat::make(
                'Personas con préstamos',
                $peopleWithPendingLoans
            )
                ->description('Personas con material pendiente')
                ->descriptionIcon('heroicon-m-users')
                ->color(
                    $peopleWithPendingLoans > 0
                    ? 'warning'
                    : 'success'
                )
                ->url(PersonResource::getUrl('index')),

        ];
    }
}