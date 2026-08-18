<?php

namespace App\Notifications;

use App\Filament\Resources\MaterialResource;
use App\Models\WarehouseStock;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WarehouseStock $warehouseStock,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->warehouseStock->loadMissing([
            'material',
            'warehouse',
        ]);

        return FilamentNotification::make()
            ->title('Stock mínimo alcanzado')
            ->body(sprintf(
                '%s - %s tiene una existencia de %.3f en %s. El mínimo configurado es %.3f.',
                $this->warehouseStock->material->code,
                $this->warehouseStock->material->name,
                (float) $this->warehouseStock->current_stock,
                $this->warehouseStock->warehouse->name,
                (float) $this->warehouseStock->minimum_stock,
            ))
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                Action::make('view')
                    ->label('Ver material')
                    ->button()
                    ->url(
                        MaterialResource::getUrl('edit', [
                            'record' =>
                                $this->warehouseStock->material_id,
                        ])
                    )
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}