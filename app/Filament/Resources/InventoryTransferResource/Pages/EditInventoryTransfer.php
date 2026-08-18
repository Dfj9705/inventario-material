<?php

namespace App\Filament\Resources\InventoryTransferResource\Pages;

use App\Filament\Resources\InventoryTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryTransfer extends EditRecord
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
