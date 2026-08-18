<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\MaterialType;
use App\Enums\MovementType;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class LoanService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly SignatureService $signatureService,
    ) {
    }

    public function create(array $data): Loan
    {
        $validated = Validator::make($data, [
            'person_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'loan_date' => ['required', 'date'],
            'expected_return_date' => [
                'nullable',
                'date',
                'after_or_equal:loan_date',
            ],
            'signature' => ['required', 'string'],
            'observations' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ])->validate();

        $userId = auth()->id();

        if ($userId === null) {
            throw new LogicException(
                'El préstamo necesita un usuario responsable.'
            );
        }

        $items = $this->normalizeItems($validated['items']);

        $signaturePath = $this->signatureService
            ->storeLoanSignature($validated['signature']);

        try {
            $loan = DB::transaction(function () use ($validated, $items, $signaturePath, $userId, ): Loan {
                $person = Person::query()
                    ->whereKey($validated['person_id'])
                    ->where('is_active', true)
                    ->first();

                if ($person === null) {
                    throw ValidationException::withMessages([
                        'person_id' => 'La persona seleccionada no está disponible.',
                    ]);
                }

                $warehouse = Warehouse::query()
                    ->whereKey($validated['warehouse_id'])
                    ->where('is_active', true)
                    ->first();

                if ($warehouse === null) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => 'La bodega seleccionada no está disponible.',
                    ]);
                }

                $stocks = $this->lockStocks(
                    warehouseId: $warehouse->getKey(),
                    items: $items,
                );

                $this->validateStocks($stocks, $items);

                $loanDate = Carbon::parse($validated['loan_date']);

                $loan = Loan::query()->create([
                    'person_id' => $person->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'loan_date' => $loanDate,
                    'expected_return_date' => filled(
                        $validated['expected_return_date'] ?? null
                    )
                        ? Carbon::parse(
                            $validated['expected_return_date']
                        )->toDateString()
                        : null,
                    'signature_path' => $signaturePath,
                    'observations' => filled(
                        $validated['observations'] ?? null
                    )
                        ? trim($validated['observations'])
                        : null,
                    'status' => LoanStatus::ACTIVE,
                    'created_by' => $userId,
                ]);

                foreach ($items as $item) {
                    /** @var WarehouseStock $stock */
                    $stock = $stocks->get($item['material_id']);

                    $loanItem = $loan->items()->create([
                        'material_id' => $item['material_id'],
                        'quantity' => $this->formatDecimal(
                            $item['quantity']
                        ),
                    ]);

                    $this->movementService->register(
                        warehouseStock: $stock,
                        type: MovementType::LOAN,
                        quantity: $item['quantity'],
                        reference: $loanItem,
                        movementDate: $loanDate,
                        observations: "Préstamo {$loan->code}",
                        userId: $userId,
                    );
                }

                return $loan;
            }, attempts: 5);
        } catch (Throwable $exception) {
            $this->signatureService->delete($signaturePath);

            throw $exception;
        }

        return $loan->load([
            'person',
            'warehouse',
            'items.material',
            'creator',
        ]);
    }

    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->map(
                fn(array $item, int $index): array => [
                    'index' => $index,
                    'material_id' => (int) $item['material_id'],
                    'quantity' => round((float) $item['quantity'], 3),
                ]
            )
            ->sortBy('material_id')
            ->values();
    }

    private function lockStocks(
        int $warehouseId,
        Collection $items
    ): Collection {
        return WarehouseStock::query()
            ->with('material')
            ->where('warehouse_id', $warehouseId)
            ->whereIn(
                'material_id',
                $items->pluck('material_id')
            )
            ->orderBy('material_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('material_id');
    }

    private function validateStocks(
        Collection $stocks,
        Collection $items
    ): void {
        foreach ($items as $item) {
            /** @var WarehouseStock|null $stock */
            $stock = $stocks->get($item['material_id']);

            if ($stock === null) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.material_id" =>
                        'El material no está asignado a la bodega seleccionada.',
                ]);
            }

            if (
                $stock->material === null
                || !$stock->material->is_active
            ) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.material_id" =>
                        'El material seleccionado no está activo.',
                ]);
            }

            if (
                $stock->material->type
                !== MaterialType::NON_CONSUMABLE
            ) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.material_id" =>
                        'Solamente pueden prestarse materiales no consumibles.',
                ]);
            }

            if (
                $item['quantity']
                > round((float) $stock->current_stock, 3)
            ) {
                throw ValidationException::withMessages([
                    "items.{$item['index']}.quantity" => sprintf(
                        'Stock insuficiente. Disponible: %.3f.',
                        (float) $stock->current_stock
                    ),
                ]);
            }
        }
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}