<?php

namespace App\Services;

use App\Models\User;
use App\Models\WarehouseStock;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

class StockNotificationService
{
    public function handle(WarehouseStock $stock): void
    {
        $stock->loadMissing([
            'material',
            'warehouse',
        ]);

        if ($stock->minimum_stock === null) {
            $this->resetNotification($stock);

            return;
        }

        $isLowStock = (float) $stock->current_stock
            <= (float) $stock->minimum_stock;

        if (!$isLowStock) {
            $this->resetNotification($stock);

            return;
        }

        if ($stock->low_stock_notified_at !== null) {
            return;
        }

        $recipients = User::query()
            ->whereHas(
                'roles',
                fn($query) => $query->whereIn('name', [
                    'Super Administrador',
                    'Administrador',
                    'Encargado de Inventario',
                ])
            )
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::sendNow(
            $recipients,
            new LowStockNotification($stock)
        );

        $stock->forceFill([
            'low_stock_notified_at' => now(),
        ])->saveQuietly();
    }

    private function resetNotification(
        WarehouseStock $stock
    ): void {
        if ($stock->low_stock_notified_at === null) {
            return;
        }

        $stock->forceFill([
            'low_stock_notified_at' => null,
        ])->saveQuietly();
    }
}