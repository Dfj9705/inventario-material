<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\MovementType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanReturn;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class LoanReturnService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
    ) {
    }

    public function create(
        Loan $loan,
        array $data,
        ?int $userId = null,
    ): LoanReturn {
        $userId ??= auth()->id();

        if ($userId === null) {
            throw new LogicException(
                'La devolución necesita un usuario responsable.'
            );
        }

        $validated = Validator::make(
            $data,
            [
                'return_date' => [
                    'required',
                    'date',
                    'before_or_equal:now',
                ],
                'observations' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
                'items' => [
                    'required',
                    'array',
                    'min:1',
                ],
                'items.*.loan_item_id' => [
                    'required',
                    'integer',
                    'distinct',
                ],
                'items.*.quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],
            ],
            [],
            [
                'return_date' => 'fecha de devolución',
                'items' => 'materiales devueltos',
                'items.*.loan_item_id' => 'material',
                'items.*.quantity' => 'cantidad devuelta',
            ],
        )->validate();

        return DB::transaction(function () use ($loan, $validated, $userId, ): LoanReturn {
            $lockedLoan = Loan::query()
                ->whereKey($loan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLoan->status === LoanStatus::RETURNED) {
                throw ValidationException::withMessages([
                    'items' => 'Este préstamo ya fue devuelto completamente.',
                ]);
            }

            $returnDate = Carbon::parse($validated['return_date']);

            if ($returnDate->lt(Carbon::parse($lockedLoan->loan_date))) {
                throw ValidationException::withMessages([
                    'return_date' =>
                        'La devolución no puede ser anterior al préstamo.',
                ]);
            }

            $requestedItems = collect($validated['items'])
                ->sortBy('loan_item_id');

            $loanItems = LoanItem::query()
                ->where('loan_id', $lockedLoan->getKey())
                ->whereIn(
                    'id',
                    $requestedItems->pluck('loan_item_id')
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($loanItems->count() !== $requestedItems->count()) {
                throw ValidationException::withMessages([
                    'items' =>
                        'Uno de los materiales no pertenece a este préstamo.',
                ]);
            }

            $loanReturn = $lockedLoan->loanReturns()->create([
                'return_date' => $returnDate,
                'observations' => filled($validated['observations'] ?? null)
                    ? trim($validated['observations'])
                    : null,
                'received_by' => $userId,
            ]);

            foreach ($requestedItems as $index => $itemData) {
                $loanItem = $loanItems->get(
                    (int) $itemData['loan_item_id']
                );

                $quantity = round(
                    (float) $itemData['quantity'],
                    3
                );

                $pendingQuantity = round(
                    (float) $loanItem->quantity
                    - (float) $loanItem->returned_quantity,
                    3
                );

                if ($pendingQuantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" =>
                            'Este material ya fue devuelto completamente.',
                    ]);
                }

                if ($quantity > $pendingQuantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'La cantidad máxima pendiente es %.3f.',
                            $pendingQuantity
                        ),
                    ]);
                }

                $warehouseStock = WarehouseStock::query()
                    ->where(
                        'warehouse_id',
                        $lockedLoan->warehouse_id
                    )
                    ->where(
                        'material_id',
                        $loanItem->material_id
                    )
                    ->first();

                if ($warehouseStock === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.loan_item_id" =>
                            'El material no tiene inventario asignado en la bodega.',
                    ]);
                }

                $returnItem = $loanReturn->items()->create([
                    'loan_item_id' => $loanItem->getKey(),
                    'quantity' => number_format(
                        $quantity,
                        3,
                        '.',
                        ''
                    ),
                ]);

                $movementObservations = sprintf(
                    'Devolución del préstamo %s.',
                    $lockedLoan->code
                );

                $this->movementService->register(
                    warehouseStock: $warehouseStock,
                    type: MovementType::RETURN ,
                    quantity: $quantity,
                    reference: $returnItem,
                    movementDate: $returnDate,
                    observations: $movementObservations,
                    userId: $userId,
                );

                $loanItem->returned_quantity = number_format(
                    (float) $loanItem->returned_quantity + $quantity,
                    3,
                    '.',
                    ''
                );

                $loanItem->save();
            }

            $hasPendingItems = LoanItem::query()
                ->where('loan_id', $lockedLoan->getKey())
                ->whereColumn('returned_quantity', '<', 'quantity')
                ->exists();

            $lockedLoan->status = $hasPendingItems
                ? LoanStatus::PARTIALLY_RETURNED
                : LoanStatus::RETURNED;

            $lockedLoan->save();

            return $loanReturn->load([
                'items.loanItem.material',
                'receivedBy',
            ]);
        }, attempts: 5);
    }
}