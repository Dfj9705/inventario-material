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
        Schema::create('loan_return_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_return_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('loan_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3);

            $table->timestamps();

            $table->unique(['loan_return_id', 'loan_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_return_items');
    }
};
