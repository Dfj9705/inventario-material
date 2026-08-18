<?php

namespace App\Filament\Resources\InventoryTransferResource\Pages;

use App\Filament\Resources\InventoryTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryTransfer extends ViewRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
