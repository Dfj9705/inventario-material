<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\InventoryTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryTransferService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
    ) {
    }

    public function create(
        WarehouseStock $sourceStock,
        array $data,
        ?int $userId = null,
    ): InventoryTransfer {
        $userId ??= auth()->id();

        if ($userId === null) {
            throw new LogicException(
                'La transferencia necesita un usuario responsable.'
            );
        }

        $validated = Validator::make(
            $data,
            [
                'destination_warehouse_id' => [
                    'required',
                    'integer',
                    'exists:warehouses,id',
                ],
                'quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],
                'transfer_date' => [
                    'required',
                    'date',
                    'before_or_equal:now',
                ],
                'observations' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ],
            [],
            [
                'destination_warehouse_id' => 'bodega destino',
                'quantity' => 'cantidad',
                'transfer_date' => 'fecha de transferencia',
                'observations' => 'observaciones',
            ],
        )->validate();

        return DB::transaction(function () use ($sourceStock, $validated, $userId, ): InventoryTransfer {
            $sourceReference = WarehouseStock::query()
                ->with(['material', 'warehouse'])
                ->findOrFail($sourceStock->getKey());

            $destinationWarehouse = Warehouse::query()
                ->whereKey($validated['destination_warehouse_id'])
                ->where('is_active', true)
                ->first();

            if ($destinationWarehouse === null) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' =>
                        'La bodega destino no está disponible.',
                ]);
            }

            if (
                $sourceReference->warehouse_id
                === $destinationWarehouse->getKey()
            ) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' =>
                        'La bodega destino debe ser diferente a la de origen.',
                ]);
            }

            if (!$sourceReference->warehouse->is_active) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' =>
                        'La bodega de origen no está activa.',
                ]);
            }

            if (!$sourceReference->material->is_active) {
                throw ValidationException::withMessages([
                    'quantity' => 'El material no está activo.',
                ]);
            }

            $quantity = round(
                (float) $validated['quantity'],
                3
            );

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' =>
                        'La cantidad debe ser de al menos 0.001.',
                ]);
            }

            $destinationStock = WarehouseStock::query()
                ->firstOrCreate(
                    [
                        'warehouse_id' =>
                            $destinationWarehouse->getKey(),
                        'material_id' =>
                            $sourceReference->material_id,
                    ],
                    [
                        'current_stock' => '0.000',
                        'minimum_stock' => null,
                        'low_stock_notified_at' => null,
                    ],
                );

            /*
             * Bloqueamos ambas existencias en orden fijo para evitar
             * interbloqueos en transferencias simultáneas opuestas.
             */
            $lockedStocks = WarehouseStock::query()
                ->with(['material', 'warehouse'])
                ->whereIn('id', [
                    $sourceReference->getKey(),
                    $destinationStock->getKey(),
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSource = $lockedStocks->get(
                $sourceReference->getKey()
            );

            $lockedDestination = $lockedStocks->get(
                $destinationStock->getKey()
            );

            if (
                $lockedSource === null
                || $lockedDestination === null
            ) {
                throw ValidationException::withMessages([
                    'quantity' =>
                        'No fue posible bloquear las existencias.',
                ]);
            }

            if ((float) $lockedSource->current_stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Stock insuficiente. Disponible: %.3f.',
                        (float) $lockedSource->current_stock
                    ),
                ]);
            }

            $transfer = InventoryTransfer::create([
                'material_id' => $lockedSource->material_id,
                'source_warehouse_id' =>
                    $lockedSource->warehouse_id,
                'destination_warehouse_id' =>
                    $lockedDestination->warehouse_id,
                'quantity' => number_format(
                    $quantity,
                    3,
                    '.',
                    ''
                ),
                'transfer_date' => Carbon::parse(
                    $validated['transfer_date']
                ),
                'observations' =>
                    filled($validated['observations'] ?? null)
                    ? trim($validated['observations'])
                    : null,
                'created_by' => $userId,
            ]);

            $this->movementService->register(
                warehouseStock: $lockedSource,
                type: MovementType::TRANSFER_OUT,
                quantity: $quantity,
                reference: $transfer,
                movementDate: $transfer->transfer_date,
                observations: sprintf(
                    'Transferencia %s hacia %s.',
                    $transfer->code,
                    $lockedDestination->warehouse->name
                ),
                userId: $userId,
            );

            $this->movementService->register(
                warehouseStock: $lockedDestination,
                type: MovementType::TRANSFER_IN,
                quantity: $quantity,
                reference: $transfer,
                movementDate: $transfer->transfer_date,
                observations: sprintf(
                    'Transferencia %s desde %s.',
                    $transfer->code,
                    $lockedSource->warehouse->name
                ),
                userId: $userId,
            );

            return $transfer->load([
                'material',
                'sourceWarehouse',
                'destinationWarehouse',
                'createdBy',
                'movements',
            ]);
        }, attempts: 5);
    }
}