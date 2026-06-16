<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Woher kam die Buchung? null = manuell erfasst.
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('type')
                ->constrained('import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });
    }
};
