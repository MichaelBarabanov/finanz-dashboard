<?php

namespace App\Imports;

use App\Imports\Contracts\StatementParser;
use App\Imports\Parsers\HanseaticPdfParser;
use App\Imports\Parsers\SparkasseGiroPdfParser;
use RuntimeException;

/**
 * Zentrale Liste aller Parser. Neue Formate hier eintragen — der Rest
 * (UI-Auswahl, Autoerkennung) zieht automatisch nach.
 */
class ParserRegistry
{
    /** @var StatementParser[] */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new SparkasseGiroPdfParser(),
            new HanseaticPdfParser(),
        ];
    }

    /** @return StatementParser[] */
    public function all(): array
    {
        return $this->parsers;
    }

    /** [format => label] für ein Auswahlfeld. */
    public function options(): array
    {
        $out = [];
        foreach ($this->parsers as $p) {
            $out[$p->format()] = $p->label();
        }

        return $out;
    }

    public function byFormat(string $format): StatementParser
    {
        foreach ($this->parsers as $p) {
            if ($p->format() === $format) {
                return $p;
            }
        }

        throw new RuntimeException("Unbekanntes Format: {$format}");
    }

    /** Autoerkennung anhand des Textinhalts; null wenn nichts passt. */
    public function detect(string $text): ?StatementParser
    {
        foreach ($this->parsers as $p) {
            if ($p->detect($text)) {
                return $p;
            }
        }

        return null;
    }
}
