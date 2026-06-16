<?php

use App\Livewire\Dashboard;
use App\Livewire\Accounts;
use App\Livewire\Transactions;
use App\Livewire\Import;
use App\Livewire\Rules;
use App\Livewire\Debts;
use App\Livewire\Spending;
use App\Imports\ImportPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/konten', Accounts::class)->name('accounts');
Route::get('/import', Import::class)->name('import');
Route::get('/ausgaben', Spending::class)->name('spending');
Route::get('/schulden', Debts::class)->name('debts');
Route::get('/korrektur', Transactions::class)->name('transactions');
Route::get('/regeln', Rules::class)->name('rules');

/**
 * Import-Commit als normaler Formular-POST (bewusst KEINE Livewire-Aktion).
 * Grund: bei großen Importen verschluckt Livewire den Button-Klick in manchen
 * Umgebungen. Ein klassischer POST ist hier bombensicher.
 * Die geparsten Zeilen liegen unter dem Token im Cache (aus dem Analyse-Schritt).
 */
Route::post('/import/commit', function (Request $request) {
    $data = $request->validate([
        'token' => 'required|string',
        'account_id' => 'required|exists:accounts,id',
        'format' => 'nullable|string',
    ]);

    $rows = Cache::get('import.'.$data['token']);
    if (! $rows) {
        return redirect()->route('import')->with('error', 'Die Vorschau ist abgelaufen. Bitte noch einmal „Auszüge parsen".');
    }

    error_log('[IMPORT] form-commit start: rows='.count($rows).' account='.$data['account_id']);

    try {
        $batch = (new ImportPipeline())->commit(
            (int) $data['account_id'],
            $data['format'] ?? 'pdf',
            null,
            $rows,
        );
    } catch (\Throwable $e) {
        report($e);
        error_log('[IMPORT] form-commit EXCEPTION: '.$e->getMessage());

        return redirect()->route('import')->with('error', 'Fehler beim Speichern: '.$e->getMessage());
    }

    Cache::forget('import.'.$data['token']);
    error_log('[IMPORT] form-commit done: saved='.$batch->row_count.' dbTotal='.\App\Models\Transaction::count());

    return redirect()->route('dashboard')->with('saved', $batch->row_count.' Buchungen übernommen.');
})->name('import.commit');
