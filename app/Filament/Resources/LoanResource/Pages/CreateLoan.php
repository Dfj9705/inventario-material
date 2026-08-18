<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Services\LoanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LoanService::class)->create($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Préstamo registrado correctamente';
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}