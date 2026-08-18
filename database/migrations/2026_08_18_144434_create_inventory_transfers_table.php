<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('source_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3);

            $table->dateTime('transfer_date');

            $table->text('observations')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'source_warehouse_id',
                'transfer_date',
            ]);

            $table->index([
                'destination_warehouse_id',
                'transfer_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
