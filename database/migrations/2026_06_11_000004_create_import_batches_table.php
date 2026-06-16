<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            // Quelle: sparkasse_giro_pdf | hanseatic_pdf | generic_csv | text_paste | ...
            $table->string('source_format');
            $table->string('file_name')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->integer('row_count')->default(0);
            // preview | committed | discarded
            $table->string('status')->default('committed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
