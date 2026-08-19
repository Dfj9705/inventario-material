<?php

namespace App\Services;

use App\Enums\MaterialType;
use App\Enums\MovementType;
use App\Models\InventoryMovement;
use App\Models\WarehouseStock;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryMovementService
{
    public function __construct(
        private readonly StockNotificationService $stockNotificationService,
    ) {
    }
    public function registerEntry(
        WarehouseStock $stock,
        float|int|string $quantity,
        ?string $observations = null,
        ?DateTimeInterface $movementDate = null,
        ?Model $reference = null,
        ?int $userId = null,
    ): InventoryMovement {
        return $this->register(
            warehouseStock: $stock,
            type: MovementType::ENTRY,
            quantity: $quantity,
            reference: $reference,
            movementDate: $movementDate,
            observations: $observations,
            userId: $userId,
        );
    }

    public function registerConsumption(
        WarehouseStock $stock,
        float|int|string $quantity,
        ?string $observations = null,
        ?DateTimeInterface $movementDate = null,
        ?Model $reference = null,
        ?int $userId = null,
    ): InventoryMovement {
        return $this->register(
            warehouseStock: $stock,
            type: MovementType::CONSUMPTION,
            quantity: $quantity,
            reference: $reference,
            movementDate: $movementDate,
            observations: $observations,
            userId: $userId,
        );
    }

    public function registerPositiveAdjustment(
        WarehouseStock $stock,
        float|int|string $quantity,
        ?string $observations = null,
        ?DateTimeInterface $movementDate = null,
        ?int $userId = null,
    ): InventoryMovement {
        return $this->register(
            warehouseStock: $stock,
            type: MovementType::ADJUSTMENT_IN,
            quantity: $quantity,
            movementDate: $movementDate,
            observations: $observations,
            userId: $userId,
        );
    }

    public function registerNegativeAdjustment(
        WarehouseStock $stock,
        float|int|string $quantity,
        ?string $observations = null,
        ?DateTimeInterface $movementDate = null,
        ?int $userId = null,
    ): InventoryMovement {
        return $this->register(
            warehouseStock: $stock,
            type: MovementType::ADJUSTMENT_OUT,
            quantity: $quantity,
            movementDate: $movementDate,
            observations: $observations,
            userId: $userId,
        );
    }

    public function register(
        WarehouseStock $warehouseStock,
        MovementType $type,
        float|int|string $quantity,
        ?Model $reference = null,
        ?DateTimeInterface $movementDate = null,
        ?string $observations = null,
        ?int $userId = null,
    ): InventoryMovement {
        $quantity = $this->normalizeQuantity($quantity);
        $userId ??= auth()->id();

        if ($userId === null) {
            throw new LogicException(
                'El movimiento necesita un usuario responsable.'
            );
        }

        return DB::transaction(function () use ($warehouseStock, $type, $quantity, $reference, $movementDate, $observations, $userId, ): InventoryMovement {
            $stock = WarehouseStock::query()
                ->with('material')
                ->whereKey($warehouseStock->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            //$this->validateMaterialType($stock, $type);

            $balanceBefore = round((float) $stock->current_stock, 3);

            $balanceAfter = round(
                $balanceBefore + ($quantity * $type->factor()),
                3
            );

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Stock insuficiente. Existencia disponible: %.3f.',
                        $balanceBefore
                    ),
                ]);
            }

            $stock->current_stock = $this->formatDecimal($balanceAfter);
            $stock->save();

            $movement = new InventoryMovement([
                'warehouse_id' => $stock->warehouse_id,
                'material_id' => $stock->material_id,
                'type' => $type,
                'quantity' => $this->formatDecimal($quantity),
                'balance_before' => $this->formatDecimal($balanceBefore),
                'balance_after' => $this->formatDecimal($balanceAfter),
                'movement_date' => $movementDate ?? now(),
                'observations' => filled($observations)
                    ? trim($observations)
                    : null,
                'created_by' => $userId,
            ]);

            if ($reference !== null) {
                $movement->reference_type = $reference->getMorphClass();
                $movement->reference_id = $reference->getKey();
            }

            $movement->save();

            $this->stockNotificationService->handle($stock);

            return $movement;
        }, attempts: 5);
    }

    private function normalizeQuantity(
        float|int|string $quantity
    ): float {
        if (!is_numeric($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser numérica.',
            ]);
        }

        $quantity = round((float) $quantity, 3);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor que cero.',
            ]);
        }

        return $quantity;
    }

    private function validateMaterialType(
        WarehouseStock $stock,
        MovementType $type
    ): void {
        if (
            $type === MovementType::CONSUMPTION
            && $stock->material->type !== MaterialType::CONSUMABLE
        ) {
            throw ValidationException::withMessages([
                'quantity' => 'Los materiales no consumibles no pueden registrarse como consumo.',
            ]);
        }

    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}