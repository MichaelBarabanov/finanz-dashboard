<?php

namespace App\Imports\Contracts;

use App\Imports\ParseResult;

/**
 * Vertrag für alle Auszugs-Parser. Neue Banken/Formate kommen als neue
 * Implementierung dazu, ohne Bestehendes anzufassen (Open/Closed).
 *
 * Eingang ist IMMER bereits extrahierter Text (PDF wurde vorher durch
 * PdfTextExtractor geschickt, Copy-Paste kommt direkt). Der Parser kennt
 * keine Dateien — nur Text rein, ParseResult raus.
 */
interface StatementParser
{
    /** Maschinen-Kennung des Formats, z.B. 'sparkasse_giro_pdf'. */
    public function format(): string;

    /** Menschlich lesbarer Name für die UI-Auswahl. */
    public function label(): string;

    /**
     * Autoerkennung: Gehört dieser Text zu diesem Parser?
     * Wird für "Format automatisch erkennen" in der Import-UI genutzt.
     */
    public function detect(string $text): bool;

    public function parse(string $text): ParseResult;
}
