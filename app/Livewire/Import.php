<?php

namespace App\Livewire;

use App\Imports\ImportPipeline;
use App\Imports\ParserRegistry;
use App\Imports\PdfTextExtractor;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Import als KOMPAKTE Zusammenfassung statt 302-Zeilen-Tabelle.
 *
 * Bewusste Architektur: die geparsten Zeilen liegen serverseitig im Cache,
 * die Komponente hält praktisch keinen Zustand (nur ein Token + Zähler).
 * Dadurch bleibt der Livewire-Roundtrip winzig und der „Übernehmen"-Klick
 * funktioniert auch bei vielen hundert Buchungen zuverlässig.
 * Feinschliff (Kategorien korrigieren) passiert danach im Korrektur-Tab.
 */
#[Title('Import')]
class Import extends Component
{
    use WithFileUploads;

    #[Validate('required|exists:accounts,id')]
    public $account_id = null;

    public string $format = '';

    #[Validate(['pdfs.*' => 'file|mimes:pdf|max:20480'])]
    public $pdfs = [];

    public string $pasteText = '';

    // --- Vorschau-Zustand (klein!) ---
    public bool $analyzed = false;
    public ?string $token = null;
    public array $fileMetas = [];
    public int $totalCount = 0;
    public int $newCount = 0;
    public int $dupCount = 0;
    public ?string $commitError = null;
    public ?string $commitInfo = null;

    public function analyze(): void
    {
        $this->validate();
        $this->commitError = null;
        $this->commitInfo = null;

        $sources = [];
        if (! empty($this->pdfs)) {
            if (! PdfTextExtractor::isAvailable()) {
                $this->addError('pdfs', 'pdftotext ist nicht verfügbar. Nutze Copy-Paste.');

                return;
            }
            $extractor = new PdfTextExtractor();
            foreach ($this->pdfs as $pdf) {
                try {
                    $sources[] = ['name' => $pdf->getClientOriginalName(), 'text' => $extractor->extract($pdf->getRealPath())];
                } catch (\Throwable $e) {
                    $this->addError('pdfs', 'Konnte „'.$pdf->getClientOriginalName().'" nicht lesen: '.$e->getMessage());

                    return;
                }
            }
        } elseif (trim($this->pasteText) !== '') {
            $sources[] = ['name' => null, 'text' => $this->pasteText];
        } else {
            $this->addError('pdfs', 'Bitte PDFs hochladen oder Text einfügen.');

            return;
        }

        $pipeline = new ImportPipeline();
        $allRows = [];
        $metas = [];
        $seen = [];
        $accountId = (int) $this->account_id;

        foreach ($sources as $src) {
            try {
                $a = $pipeline->analyze($accountId, $src['text'], $this->format !== '' ? $this->format : null);
            } catch (\Throwable $e) {
                $this->addError('pdfs', ($src['name'] ? '„'.$src['name'].'": ' : '').$e->getMessage());

                return;
            }
            $fileDups = 0;
            foreach ($a['rows'] as $row) {
                $hash = $row['dedup_hash'];
                if (isset($seen[$hash])) {
                    $row['is_duplicate'] = true;
                }
                $seen[$hash] = true;
                if ($row['is_duplicate']) {
                    $fileDups++;
                }
                $row['source'] = $src['name'];
                $allRows[] = $row;
            }
            $metas[] = [
                'name' => $src['name'], 'format' => $a['format'], 'label' => $a['label'],
                'balance_ok' => $a['balance_ok'],
                'period_start' => $a['period_start'], 'period_end' => $a['period_end'],
                'count' => count($a['rows']), 'dups' => $fileDups,
            ];
        }

        if ($allRows === []) {
            $this->addError('pdfs', 'Keine Buchungen erkannt.');

            return;
        }

        usort($allRows, fn ($x, $y) => strcmp($x['booking_date'], $y['booking_date']));

        $this->token = (string) Str::uuid();
        Cache::put('import.'.$this->token, $allRows, now()->addHours(2));

        $this->dupCount = collect($allRows)->where('is_duplicate', true)->count();
        $this->totalCount = count($allRows);
        $this->newCount = $this->totalCount - $this->dupCount;
        $this->fileMetas = $metas;
        $this->analyzed = true;
        $this->reset('pdfs');

        error_log('[IMPORT] analyze ok: files='.count($sources).' rows='.$this->totalCount.' new='.$this->newCount.' token='.$this->token);
    }

    public function commit()
    {
        if (! $this->analyzed) {
            error_log('[IMPORT] commit: not analyzed');

            return null;
        }

        $this->commitError = null;

        $rows = $this->token ? (Cache::get('import.'.$this->token) ?: []) : [];
        if ($rows === []) {
            $this->commitError = 'Die Vorschau ist abgelaufen. Bitte noch einmal „Auszüge parsen".';

            return null;
        }

        error_log('[IMPORT] commit start: rows='.count($rows));

        try {
            $format = $this->fileMetas[0]['format'] ?? ($this->format ?: 'pdf');
            $names = collect($this->fileMetas)->pluck('name')->filter()->implode(', ');
            $batch = (new ImportPipeline())->commit((int) $this->account_id, $format, $names ?: null, $rows);
        } catch (\Throwable $e) {
            report($e);
            error_log('[IMPORT] commit EXCEPTION: '.$e->getMessage());
            $this->commitError = 'Fehler beim Speichern: '.$e->getMessage();

            return null;
        }

        error_log('[IMPORT] commit done: saved='.$batch->row_count.' dbTotal='.\App\Models\Transaction::count());

        Cache::forget('import.'.$this->token);
        session()->flash('saved', "{$batch->row_count} Buchungen übernommen.");

        // Voller Redirect zur Übersicht – die Daten sind sichtbar und der
        // Komponenten-Zustand wird sauber zurückgesetzt.
        return redirect()->route('dashboard');
    }

    public function cancel(): void
    {
        if ($this->token) {
            Cache::forget('import.'.$this->token);
        }
        $this->analyzed = false;
        $this->token = null;
        $this->fileMetas = [];
        $this->totalCount = 0;
        $this->newCount = 0;
        $this->dupCount = 0;
    }

    public function render()
    {
        // Nur eine kleine Stichprobe zum Anzeigen (read-only, keine Livewire-Bindungen).
        $sample = [];
        if ($this->analyzed && $this->token) {
            $sample = array_slice(Cache::get('import.'.$this->token) ?: [], 0, 12);
        }

        return view('livewire.import', [
            'accounts' => Account::orderBy('name')->get(),
            'formats' => (new ParserRegistry())->options(),
            'sample' => $sample,
        ]);
    }
}
