<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['giro', 'credit_card'])->default('giro');
            $table->string('bank')->nullable();
            $table->string('iban_last4', 4)->nullable();
            // Geld immer als Cent-Integer, niemals Float.
            $table->bigInteger('opening_balance_cents')->default(0);
            $table->bigInteger('credit_limit_cents')->nullable(); // nur bei Kreditkarte
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
