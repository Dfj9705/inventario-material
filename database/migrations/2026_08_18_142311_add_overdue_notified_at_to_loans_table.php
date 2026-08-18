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
        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('overdue_notified_at')
                ->nullable()
                ->after('status');

            $table->index([
                'status',
                'expected_return_date',
                'overdue_notified_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex([
                'status',
                'expected_return_date',
                'overdue_notified_at',
            ]);

            $table->dropColumn('overdue_notified_at');
        });
    }
};
