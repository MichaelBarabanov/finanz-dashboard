# Finanz Dashboard
<img width="1893" height="888" alt="Screenshot 2026-06-17 014952" src="https://github.com/user-attachments/assets/572c7a75-2b0f-4f54-adff-b5dec481ccbb" />

Privates, lokales Finanz-Dashboard. Laravel 11 + Livewire 3 + SQLite, lauffähig über Docker.
Konzept und Roadmap: siehe [KONZEPT.md](KONZEPT.md).

Aktueller Stand: **Phase 0–4** — Konten, manuelle Buchungen, Übersicht mit Kennzahlen + 6-Monats-Diagramm, **PDF-Import** (Sparkasse Giro + Hanseatic Karte, Vorschau mit Salden-Prüfsumme, Dedup, nie Auto-Commit), **Regel-Engine** die aus Korrekturen lernt, **Schulden & Raten** (feste Raten + flexible Tilgung, Ratenplan, Fälligkeiten, Gesamtbelastung). Optik: **„Dark Glass / Neon"**-Design mit Hell/Dunkel-Umschalter, SPA-Navigation (`wire:navigate`).

---

## Schnellstart (Docker)

Voraussetzung: Docker + Docker Compose.

```bash
docker compose up --build
```

Beim ersten Start dauert es ein paar Minuten (Composer lädt die Abhängigkeiten).
Danach erreichbar unter: **http://localhost:8000**

Der Container erledigt automatisch:
1. `.env` anlegen (falls nicht vorhanden)
2. `composer install`
3. `APP_KEY` erzeugen
4. SQLite-Datei `database/finanz.sqlite` anlegen
5. Migrationen + Standardkategorien einspielen
6. App starten (`php artisan serve`)

Stoppen: `Strg+C`, oder `docker compose down`.

---

## Wo liegen meine Daten?

Alles in **einer Datei**: `database/finanz.sqlite`.

Das ist die komplette Datenbank. Zum Mitnehmen auf den anderen Rechner einfach
diese Datei kopieren — fertig. (Empfehlung: nicht kopieren, während die App läuft.)

Die Datei ist bewusst in `.gitignore` — Finanzdaten gehören nicht ins Repo.

---

## Erste Schritte in der App

1. **Konten** → ein Girokonto und ggf. die Kreditkarte anlegen (mit Anfangssaldo).
2. **Buchungen** → erste Einnahmen/Ausgaben manuell erfassen, Kategorie wählen.
3. **Übersicht** → Kennzahlen, Top-Kategorien, Diagramm.

> Hinweis Vorzeichen: Bei "Ausgabe" und "Transfer" wird der Betrag automatisch
> als negativ gespeichert, bei "Einnahme" als positiv. Du gibst immer den
> positiven Betrag ein und wählst die Art.

> Interne Transfers (z.B. Girokonto → Kreditkarte) als Art **Transfer** mit Häkchen
> "interner Transfer" erfassen — die werden in Auswertungen nicht als Ausgabe gezählt.

---

## Technische Eckpunkte

- **Geld** wird immer als Cent-Integer (`*_cents`) gespeichert, nie als Float.
- **Saldo** wird abgeleitet (Anfangssaldo + Summe Buchungen), nicht redundant gespeichert.
- **Tailwind** läuft im MVP über das Play-CDN (kein Node-Build). Später optional durch
  einen echten Build ersetzen.
- Kein User-Login: bewusst weggelassen, da Single-User lokal.

---

## Lokal ohne Docker (optional)

Falls PHP 8.2+ und Composer lokal vorhanden:

```bash
composer install
cp .env.example .env
# In .env DB_DATABASE auf einen absoluten Pfad zur finanz.sqlite setzen
php artisan key:generate
touch database/finanz.sqlite
php artisan migrate --seed
php artisan serve
```

---

## Import (Phase 2, gebaut)

Menü **Import**: Konto wählen → PDF hochladen (oder Text einfügen) → **Format wird
automatisch erkannt** (Sparkasse Giro / Hanseatic Karte) → Vorschau prüfen → übernehmen.

- **Salden-Prüfsumme**: die Summe der geparsten Buchungen muss der Saldo-Differenz des
  Auszugs entsprechen. Stimmt sie nicht, warnt die Vorschau — Schutz vor Fehlparsing.
- **Dedup**: bereits importierte Buchungen werden markiert und standardmäßig abgewählt
  (kein doppelter Import desselben Auszugs).
- **Vorkategorisierung**: einfache Schlüsselwort-Regeln (Platzhalter bis Phase 3) plus
  Erkennung interner Transfers (Kreditkarten-Ausgleich Giro ↔ Karte).
- Voraussetzung im Container: `pdftotext` (poppler-utils, bereits im Image).
- Neue Banken/Formate: einen Parser implementieren (`App\Imports\Contracts\StatementParser`)
  und in `App\Imports\ParserRegistry` eintragen.

## Regeln (Phase 3, gebaut)

Menü **Regeln**: Kategorisierungs-Regeln (`category_rules`) nach Priorität, höchster
Treffer gewinnt. Greifen automatisch beim Import (`RuleEngine` ersetzt den alten
`SimpleCategorizer`). Gelernt wird aus Korrekturen: kategorisierst du in der
**Buchungsliste** oder der **Import-Vorschau** um, wird angeboten, daraus eine Regel zu
machen — die dann **sofort rückwirkend** alle passenden Buchungen umkategorisiert.
Interne Transfers (Kreditkarten-Ausgleich) werden weiterhin strukturell erkannt.

## Schulden & Raten (Phase 4, gebaut)

Menü **Schulden**: zwei Schuldtypen — *feste Raten* (`installment`, z.B. PayPal eBay)
mit automatisch generiertem Ratenplan, und *flexible Tilgung* (`revolving`, z.B.
Kreditkarte), bei der Tilgungen manuell erfasst werden. Restschuld, Fortschritt,
verbleibende Raten und voraussichtliches Enddatum sind abgeleitet (nicht gespeichert).
Das Dashboard zeigt Gesamtschulden + fällige Raten der nächsten 14 Tage.
Geseedet sind die zwei bekannten Schulden (PayPal eBay 600 €/6×100, Hanseatic ~1.260 €).

## Design & Dark Mode

„Dark Glass / Neon": dunkle Basis, Glas-Karten (`.glass`), kühler Akzent, dezente Glows.
Zentrales Design-System als CSS-Variablen im Layout (`resources/views/components/layouts/app.blade.php`)
— Views nutzen semantische Klassen (`.glass`, `.field`, `.btn-accent`, `.kpi`, `.pos/.neg`).
Umschalter 🌙/☀️ oben rechts, Präferenz in `localStorage` (Standard: dunkel).
Tab-Wechsel ohne Vollreload über `wire:navigate`.

## Nächste Phasen (siehe KONZEPT.md)
4. Schulden- & Raten-Tracking
5. Kreditkarten-Abrechnung (PDF) + Analyse
6. Graphen/Statistiken ausbauen
7. Einnahmen-Modell + Prognosen
8. Ziele
