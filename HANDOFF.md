# Übergabe-Prompt für die Weiterarbeit

Kopiere den folgenden Block als erste Nachricht in das neue Projekt auf dem anderen Rechner.

---

Du arbeitest als technischer Co-Developer (Senior Fullstack) an meinem privaten **Finanz-Dashboard**. Der komplette Projektordner liegt dir bereits vor. Lies zuerst `KONZEPT.md` (Architektur + Roadmap) und `README.md` (Start + aktueller Stand). Dieser Prompt ergänzt den Kontext, der in keiner Datei steht.

## Wer ich bin / wie ich arbeite
Backend/Fullstack-Dev (PHP, MySQL/MariaDB, Shopware/JTL, APIs, Git). Ich verstehe Code, will saubere Struktur und kurze, direkte Erklärungen. Kein Motivationsgelaber, keine Overengineering-Lösungen. Wenn eine Idee schlecht ist, sag es klar. Antworten direkt und praxisnah.

## Was das Projekt ist
Privates Single-User Finanz-Dashboard, **rein lokal**, kein SaaS für viele Nutzer. Ziel: Girokonto + Kreditkarte tracken, Kontoauszüge/Abrechnungen importieren (CSV/PDF/Copy-Paste), Ausgaben automatisch kategorisieren, Schulden & Raten verfolgen, Graphen, Prognosen, Ziele. **Keine echten Bankzugänge** — Import nur über manuell exportierte Auszüge.

## Getroffene Architektur-Entscheidungen (und warum)
- **Laravel 11 + Livewire 3 + Tailwind** — ein Framework für Backend + reaktive UI, kein separates Frontend. Passt zu meinem PHP-Stack.
- **SQLite, eine Datei** (`database/finanz.sqlite`) statt MariaDB. Grund: Für genau einen Nutzer lokal ist ein DB-Server unnötig. Zusätzlich löst es mein Portabilitätsproblem — ich arbeite an **zwei Rechnern**, und eine einzelne DB-Datei kann ich einfach kopieren/synchen. Wechsel zu MariaDB später = Konfig-Change, kein Lock-in.
- **Docker (ein Container, php:8.3-cli + `php artisan serve`)**. Entrypoint macht beim ersten Start automatisch: composer install, key:generate, SQLite anlegen, migrate --seed, serve. Kein nginx — für Single-User unnötig.
- **Parsing/Kategorisierung regelbasiert & offline** (keine AI im MVP). Grund: keine API-Kosten, Finanzdaten verlassen den Rechner nicht. AI-Fallback ist als spätere optionale Erweiterung vorgesehen, nicht jetzt.
- **Kein Login** — bewusst weggelassen, Single-User lokal.

## Wichtige technische Konventionen (unbedingt einhalten)
- **Geld immer als Cent-Integer** (`*_cents`), niemals Float. Eingaben über `euros_to_cents()`, Anzeige über `money_format_de()` (siehe `app/helpers.php`).
- **Saldo wird abgeleitet** (Anfangssaldo + Summe Buchungen), nicht redundant gespeichert → kann nie inkonsistent werden.
- **Interne Transfers** (z.B. Giro → Kreditkarte) haben das Flag `is_internal_transfer` und werden aus Einnahmen/Ausgaben-Auswertungen ausgeschlossen, sonst wird alles doppelt gezählt. Die Scopes `realIncome()` / `realExpenses()` im `Transaction`-Model setzen das schon um.
- **Deutsche Formate**: Datum `TT.MM.JJJJ`, Dezimal-Komma. Zeitzone Europe/Berlin.
- **Dedup** über `dedup_hash` (sha1 aus Konto + Datum + Betrag + normalisiertem Text), damit derselbe Auszug nicht doppelt importiert wird.
- Edge Cases, die schon mitgedacht sind: Erstattungen = negative Ausgabe (nicht als Einkommen), Doppelimport, Teilmonate bei Prognosen kennzeichnen, Gehalt „Ende Monat" mit flexiblem Zahltag.

## Bewusste MVP-Abkürzungen (kein Versehen)
- **Tailwind über Play-CDN** statt Node-Build (kein npm-Schritt). Später optional durch echten Build ersetzen.
- Netto aus Brutto wird **nicht** exakt berechnet (Steuerklasse etc.) — trage ich manuell ein.

## Aktueller Stand: Phase 0 + 1 ist gebaut, aber NOCH NICHT ausgeführt
Die vorherige Session hatte keine PHP-Umgebung zum Testen. Der Code ist Standard-Laravel-11 + Livewire-3, aber bitte beim ersten `docker compose up --build` auf Fehler achten und ggf. fixen. Vorhanden:
- Migrations + Models: `accounts`, `categories`, `transactions`
- Seeder: 15 Standardkategorien (idempotent, mit Fixkosten-Flag + Farben)
- Livewire-Komponenten: `Dashboard` (Übersicht, Kennzahlen, Top-Kategorien, letzte Buchungen, 6-Monats-Chart via Chart.js), `Accounts` (CRUD), `Transactions` (manuelle Erfassung, Filter Konto/Monat, Inline-Kategorie ändern, löschen, Pagination)

## Roadmap / nächste Phasen (Reihenfolge aus KONZEPT.md)
2. **Import**: Sparkassen-CSV zuerst (verlässlich), dann Copy-Paste-Text. Pipeline: Parser → normalisiertes DTO → Dedup → Categorizer → **Vorschau (nie Auto-Commit!)** → speichern. Parser hinter einem Interface `StatementParser` (supports/parse), damit weitere Banken/Formate andockbar sind.
3. **Kategorisierung**: Regel-Engine (`category_rules`), lernt aus manuellen Korrekturen („künftig ARAL → Sprit?").
4. **Schulden & Raten**: `debts` + `debt_payments`, Ratenplan generieren, Fälligkeiten, Gesamtbelastung.
5. **Kreditkarte**: PDF-Parsing (pdftotext, ist im Docker-Image schon installiert) — erst mit echtem Abrechnungs-Muster bauen, PDF-Layouts sind brittle.
6. Graphen ausbauen. 7. Einnahmen-Modell + Prognosen. 8. Ziele.

## Meine konkreten Daten (für später relevant)
- Aktuelles Netto ca. 2.640 €, Gehalt meist Ende des Monats. Ab 01.07.2026: 4.000 € brutto/Monat.
- Schuld 1: „PayPal eBay", 600 €, 6× 100 €, Abbuchung 21., Start 21.05.2026.
- Schuld 2: „Kreditkarte Hanseatic", ca. 1.260 €, flexible Tilgung, Abbuchung 25., Start 01.06.2026.

## Jetzt
Bestätige kurz, dass du `KONZEPT.md` und `README.md` gelesen hast, dann lass uns das Projekt einmal mit `docker compose up --build` starten und Fehler beheben. Wenn es läuft, machen wir mit **Phase 2 (CSV-Import)** weiter.

---

(Ende des Übergabe-Prompts.)
