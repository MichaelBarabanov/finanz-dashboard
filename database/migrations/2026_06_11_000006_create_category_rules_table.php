<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            // Welches Feld der Buchung wird geprüft?
            $table->string('field')->default('counterparty'); // counterparty|description|raw_text
            // Wie wird verglichen?
            $table->string('match_type')->default('contains'); // contains|regex|exact|starts_with
            $table->string('pattern');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            // Regel nur für einen Kontotyp? null = alle.
            $table->string('account_type')->nullable(); // giro|credit_card|null
            // Höhere Priorität gewinnt bei Mehrfachtreffer.
            $table->integer('priority')->default(0);
            // Aus einer Korrektur gelernt vs. selbst/seed angelegt.
            $table->boolean('auto_created')->default(false);
            $table->timestamps();

            $table->index(['priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
