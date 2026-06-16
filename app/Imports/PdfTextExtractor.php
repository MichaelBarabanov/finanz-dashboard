<?php

namespace App\Imports;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Dünner Wrapper um `pdftotext` (poppler-utils, im Docker-Image installiert).
 *
 * Bewusst KEIN PHP-PDF-Parser: pdftotext -layout hält bei beiden bekannten
 * Auszugs-Layouts (Sparkasse-Giro, Hanseatic-Karte) die Spalten zuverlässig
 * ausgerichtet, was die zeilenbasierten Parser erst robust macht.
 */
class PdfTextExtractor
{
    /**
     * @param  string  $pdfPath  Absoluter Pfad zur PDF-Datei.
     * @param  bool  $layout  -layout erhält die Spaltenausrichtung (Standard, empfohlen).
     */
    public function extract(string $pdfPath, bool $layout = true): string
    {
        if (! is_file($pdfPath)) {
            throw new RuntimeException("PDF nicht gefunden: {$pdfPath}");
        }

        $args = ['pdftotext'];
        if ($layout) {
            $args[] = '-layout';
        }
        // UTF-8 erzwingen, sonst kaputte Umlaute; '-' = Ausgabe nach stdout.
        array_push($args, '-enc', 'UTF-8', '-nopgbrk', $pdfPath, '-');

        $result = Process::run($args);

        if (! $result->successful()) {
            throw new RuntimeException(
                'pdftotext fehlgeschlagen: '.trim($result->errorOutput() ?: $result->output())
            );
        }

        return $result->output();
    }

    public static function isAvailable(): bool
    {
        return Process::run(['bash', '-lc', 'command -v pdftotext'])->successful();
    }
}
