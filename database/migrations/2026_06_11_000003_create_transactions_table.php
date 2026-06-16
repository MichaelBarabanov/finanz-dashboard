<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->date('booking_date');
            $table->date('value_date')->nullable();
            // signed: Ausgabe negativ, Einnahme positiv. Cent-Integer.
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('counterparty')->nullable();
            $table->text('description')->nullable();
            $table->text('raw_text')->nullable(); // Originalzeile bei Import
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('payment_method', [
                'card', 'transfer', 'direct_debit', 'paypal', 'cash', 'standing_order',
            ])->nullable();
            $table->enum('type', ['income', 'expense', 'transfer'])->default('expense');
            // Interner Transfer (z.B. Giro -> Kreditkarte): zählt NICHT als Ausgabe.
            $table->boolean('is_internal_transfer')->default(false);
            $table->boolean('is_manual')->default(true);
            $table->string('dedup_hash', 40)->nullable()->index();
            $table->timestamps();

            $table->index(['account_id', 'booking_date']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
