<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // installment = feste Raten, revolving = flexible Tilgung (z.B. Kreditkarte)
            $table->string('type')->default('installment');
            $table->bigInteger('total_amount_cents');          // Gesamtschuld zu Beginn (Cent)
            $table->integer('installment_count')->nullable();   // nur bei installment
            $table->bigInteger('monthly_amount_cents')->nullable(); // feste Rate
            $table->unsignedTinyInteger('payment_day')->default(1); // Tag im Monat
            $table->date('start_date');
            $table->decimal('interest_rate', 5, 2)->nullable(); // p.a., bei revolving relevant
            $table->foreignId('linked_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('status')->default('active');        // active | paid | paused
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
