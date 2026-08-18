<?php

namespace App\Notifications;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OverdueLoanNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Loan $loan,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->loan->loadMissing('person');

        return FilamentNotification::make()
            ->title('Préstamo vencido')
            ->body(sprintf(
                'El préstamo %s, entregado a %s, venció el %s.',
                $this->loan->code,
                $this->loan->person->name,
                $this->loan->expected_return_date
                    ->format('d/m/Y H:i'),
            ))
            ->danger()
            ->icon('heroicon-o-clock')
            ->actions([
                Action::make('view')
                    ->label('Ver préstamo')
                    ->button()
                    ->url(
                        LoanResource::getUrl('view', [
                            'record' => $this->loan,
                        ])
                    )
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}