<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\OverdueLoanNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotifyOverdueLoans extends Command
{
    protected $signature = 'loans:notify-overdue';

    protected $description =
        'Notifica los préstamos vencidos pendientes de devolución';

    public function handle(): int
    {
        $notifiedLoans = 0;

        Loan::query()
            ->whereIn('status', [
                LoanStatus::ACTIVE->value,
                LoanStatus::PARTIALLY_RETURNED->value,
            ])
            ->whereNotNull('expected_return_date')
            ->where('expected_return_date', '<', now())
            ->whereNull('overdue_notified_at')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($loans) use (&$notifiedLoans): void {
                    foreach ($loans as $loan) {
                        DB::transaction(
                            function () use ($loan, &$notifiedLoans, ): void {
                                $lockedLoan = Loan::query()
                                    ->whereKey($loan->getKey())
                                    ->lockForUpdate()
                                    ->first();

                                if (
                                    $lockedLoan === null
                                    || !$lockedLoan->isOverdue()
                                    || $lockedLoan
                                        ->overdue_notified_at !== null
                                ) {
                                    return;
                                }

                                $recipients = User::query()
                                    ->where(
                                        function (Builder $query) use ($lockedLoan): void {
                                            $query->whereHas(
                                                'roles',
                                                fn(
                                                Builder $roleQuery
                                            ) => $roleQuery
                                                    ->whereIn('name', [
                                                        'Super Administrador',
                                                        'Administrador',
                                                        'Encargado de Inventario',
                                                    ])
                                            );

                                            if (
                                                $lockedLoan->created_by
                                                !== null
                                            ) {
                                                $query->orWhereKey(
                                                    $lockedLoan->created_by
                                                );
                                            }
                                        }
                                    )
                                    ->get();

                                if ($recipients->isEmpty()) {
                                    return;
                                }

                                Notification::sendNow(
                                    $recipients,
                                    new OverdueLoanNotification(
                                        $lockedLoan
                                    )
                                );

                                $lockedLoan->forceFill([
                                    'overdue_notified_at' => now(),
                                ])->saveQuietly();

                                $notifiedLoans++;
                            },
                            attempts: 5
                        );
                    }
                }
            );

        $this->info(
            "{$notifiedLoans} préstamo(s) vencido(s) notificado(s)."
        );

        return self::SUCCESS;
    }
}