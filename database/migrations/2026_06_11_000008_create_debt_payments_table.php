<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->date('due_date');
            $table->bigInteger('amount_cents');                 // geplante/echte Rate (Cent)
            $table->boolean('paid')->default(false);
            $table->date('paid_date')->nullable();
            // Verknüpfung zur echten Buchung, sobald gematcht.
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['debt_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
