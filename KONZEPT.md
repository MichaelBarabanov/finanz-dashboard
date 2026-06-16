# Finanz-Dashboard — Architektur & MVP-Konzept

Stand: 11.06.2026
Autor: technischer Co-Developer
Ziel: Privates Single-User Finanz-Dashboard, lokal, erweiterbar, nicht overengineered.

---

## 0. Grundentscheidungen (festgelegt)

| Thema | Entscheidung | Begründung |
|---|---|---|
| Framework | **Laravel 11 + Livewire 3 + Tailwind** | Ein Framework für Backend + reaktive UI. Kein separates Frontend-Projekt, kein API-Layer nötig. Du bist PHP-Dev → kein Stackwechsel. |
| Datenbank | **SQLite (eine Datei)** | Genau deine "DB-Datei beim Start laden"-Idee. Kein DB-Server. Portabel zwischen deinen zwei Rechnern: eine Datei kopieren/synchen, fertig. |
| Deployment | **Docker (ein Container)** | PHP-App im Container, SQLite-Datei als gemountetes Volume. Reproduzierbar, läuft überall gleich. |
| Parsing/Kategorisierung | **Regelbasiert, offline** | Keine API-Kosten, Finanzdaten verlassen den Rechner nicht. AI-Fallback später als optionales Modul. |
| Charts | **Chart.js** (via Vite eingebunden) | Leichtgewichtig, kein Build-Schmerz, reicht für alle gewünschten Diagramme. |
| Auth | **Eine einfache Passwort-Hürde** (.env-Wert) oder gar keine im lokalen Betrieb | Single-User lokal. Kein User-Management, kein Registrierungsflow. Bewusst weggelassen. |

**Warum nicht MariaDB im Docker:** Für genau einen Nutzer lokal ist ein DB-Server unnötige Komplexität. SQLite kann alles, was du hier brauchst (Joins, Indexe, Transaktionen, Views), und löst dein Portabilitätsproblem geschenkt. Wenn das Tool jemals mehrnutzerfähig oder server-gehostet werden soll, ist der Wechsel SQLite → MariaDB in Laravel ein Konfig-Change plus Migrationslauf. Kein Lock-in.

**Portabilität zwischen deinen Rechnern:** Quelle der Wahrheit ist genau eine Datei: `database/finanz.sqlite`. Drei Optionen, von simpel nach komfortabel:
1. Datei manuell kopieren (USB/Cloud-Ordner).
2. Projektordner in einen privaten Sync-Ordner (Nextcloud/Syncthing) legen — DB wird automatisch mitsynchronisiert. **Nicht** Dropbox/GDrive bei geöffneter App (Korruptionsrisiko bei gleichzeitigem Schreibzugriff).
3. Privates Git-Repo: Code + leere DB im Repo, die echte `finanz.sqlite` in `.gitignore`, separat gesynced. Finanzdaten gehören nicht ins Git.

---

## 1. Tech Stack — konkret

```
Docker
└── app (PHP 8.3-fpm + nginx, oder php artisan serve für MVP)
    ├── Laravel 11
    ├── Livewire 3        → reaktive UI ohne SPA
    ├── Tailwind 3        → Styling
    ├── Chart.js          → Diagramme
    └── poppler-utils     → pdftotext für PDF-Import
DB: SQLite-Datei (gemountetes Volume)
```

Bibliotheken, die wir gezielt einsetzen:
- `league/csv` — robustes CSV-Parsing (Trennzeichen, Encoding, Header-Mapping).
- `smalot/pdfparser` **oder** das CLI-Tool `pdftotext` — PDF → Text. Start mit `pdftotext`, weil verlässlicher bei Tabellen-Layouts.
- `nesbot/carbon` (in Laravel enthalten) — Datums-Handling, deutsche Formate.

Bewusst **nicht** im MVP: Queues/Redis, Elasticsearch, Microservices, eigenes Frontend-Framework, Multi-Tenancy. Alles Overkill für einen Nutzer.

---

## 2. Datenmodell

Kerntabellen. Beträge werden **signed** gespeichert (Ausgabe negativ, Einnahme positiv) und in **Cent als Integer** (`amount_cents`) — niemals Float für Geld (Rundungsfehler). Anzeige formatiert die App.

### accounts
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| name | string | "Sparkasse Giro", "Hanseatic Mastercard" |
| type | enum | `giro` \| `credit_card` |
| bank | string | |
| iban_last4 | string null | nur letzte 4 Stellen, nie volle IBAN nötig |
| credit_limit_cents | int null | nur bei Kreditkarte |
| balance_cents | int | aktueller Saldo, aus Transaktionen abgeleitet/gepflegt |
| currency | string | default EUR |

### transactions
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| account_id | FK | |
| booking_date | date | Buchungsdatum |
| value_date | date null | Wertstellung |
| amount_cents | int | signed |
| currency | string | |
| counterparty | string null | Händler/Empfänger |
| description | text null | Verwendungszweck |
| raw_text | text null | Originalzeile, für Nachvollziehbarkeit/Reparsing |
| category_id | FK null | |
| payment_method | enum null | `card` \| `transfer` \| `direct_debit` \| `paypal` \| `cash` \| `standing_order` |
| type | enum | `income` \| `expense` \| `transfer` |
| import_batch_id | FK null | woher kam die Buchung |
| dedup_hash | string | Index, verhindert Doppelimport |
| is_manual | bool | manuell erfasst vs. importiert |
| is_internal_transfer | bool | z.B. Giro → Kreditkarte, nicht als Ausgabe zählen |

### categories
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| name | string | Einkommen, Auto, Sprit, Versicherung, Kredit/Raten, PayPal, Essen, Freizeit, Motorrad, Gym, Gesundheit, Altersvorsorge, Wohnen, Abos, Sonstiges |
| parent_id | FK null | z.B. Sprit unter Auto |
| color | string | für Charts |
| icon | string null | |
| is_system | bool | Standardkategorien vs. selbst angelegt |

### category_rules
Die Regel-Engine. Wird beim Import angewandt und lernt aus manuellen Korrekturen.
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| field | enum | `counterparty` \| `description` \| `raw_text` |
| match_type | enum | `contains` \| `regex` \| `exact` \| `starts_with` |
| pattern | string | z.B. "ARAL", "PAYPAL", "Allianz" |
| category_id | FK | |
| account_type | enum null | Regel ggf. nur für Giro oder nur Karte |
| priority | int | höhere Priorität gewinnt bei Mehrfachtreffer |
| auto_created | bool | aus Korrektur gelernt vs. selbst angelegt |

### credit_card_statements
Abrechnungs-Ebene (zusätzlich zu den einzelnen Umsätzen).
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| account_id | FK | |
| period_start / period_end | date | Abrechnungszeitraum |
| old_balance_cents | int | alter Saldo |
| new_balance_cents | int | neuer Saldo |
| total_charges_cents | int | neue Umsätze im Monat |
| payment_amount_cents | int | Abbuchungsbetrag |
| interest_cents | int | Zinsen |
| due_date | date | Fälligkeit |

### debts
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| name | string | "PayPal eBay", "Kreditkarte Hanseatic" |
| type | enum | `installment` (feste Raten) \| `revolving` (flexible Tilgung, z.B. Karte) |
| total_amount_cents | int | Gesamtschuld zu Beginn |
| installment_count | int null | nur bei `installment` |
| monthly_amount_cents | int null | feste Rate, bei `installment` |
| payment_day | int | Tag im Monat (z.B. 21) |
| start_date | date | |
| interest_rate | decimal null | p.a., bei revolving relevant |
| linked_account_id | FK null | z.B. Karten-Schuld zeigt auf das Karten-Konto |
| status | enum | `active` \| `paid` \| `paused` |

### debt_payments
Ratenplan. Bei `installment` beim Anlegen generiert. Bei `revolving` aus echten Zahlungen gefüllt.
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| debt_id | FK | |
| due_date | date | |
| amount_cents | int | geplante Rate |
| paid | bool | |
| paid_date | date null | |
| transaction_id | FK null | Verknüpfung zur echten Buchung, sobald gematcht |

### incomes
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| label | string | "Gehalt eRock" |
| net_cents | int | Netto |
| gross_cents | int null | Brutto |
| frequency | enum | `monthly` \| `once` |
| payday | int null | Tag im Monat, "Ende Monat" = z.B. 28/letzter |
| valid_from | date | z.B. neues Gehalt ab 01.07.2026 |
| valid_to | date null | altes Gehalt endet, wenn neues startet |

### goals
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| name | string | "Kreditkarte auf 0", "Notgroschen 3.000" |
| target_cents | int | |
| current_cents | int | manuell oder aus verknüpfter Schuld/Konto abgeleitet |
| deadline | date null | |
| priority | enum | `low` \| `medium` \| `high` |
| status | enum | `active` \| `done` \| `paused` |
| linked_debt_id | FK null | "Karte auf 0" = Fortschritt aus Schuld ableiten |

### import_batches
| Feld | Typ | Notiz |
|---|---|---|
| id | PK | |
| account_id | FK | |
| source_format | enum | `sparkasse_csv` \| `hanseatic_pdf` \| `generic_csv` \| `text_paste` |
| file_name | string null | |
| imported_at | datetime | |
| row_count | int | |
| status | enum | `preview` \| `committed` \| `discarded` |

---

## 3. Import- & Parsing-Strategie

**Goldene Regel: Import committet nie automatisch.** Jeder Import läuft über eine Vorschau, die du bestätigst. Bei Finanzdaten ist ein falsch geparster Stapel teurer als zwei Klicks mehr.

### Pipeline
```
Rohinput (Datei-Upload | Copy-Paste-Text)
   ↓
Parser (je Format eine Klasse)
   ↓
Normalisierung → einheitliches TransactionData-DTO
   ↓
Dedup-Check (dedup_hash gegen DB)
   ↓
Categorizer (Regel-Engine setzt category_id)
   ↓
VORSCHAU im UI  ← du korrigierst/bestätigst
   ↓
Persist + import_batch (status committed)
```

### Parser-Architektur (erweiterbar)
Ein Interface, mehrere Implementierungen — neue Banken/Formate steckst du später dazu, ohne Bestehendes anzufassen:

```php
interface StatementParser {
    public function supports(string $format): bool;
    public function parse(string $content): array; // TransactionData[]
}
```
Implementierungen (in dieser Reihenfolge bauen):
1. `SparkasseCsvParser` — CSV ist verlässlich, fester Spalten-Header. **Starte hier.**
2. `GenericTextParser` — Copy-Paste aus Banking-App, zeilenweise Heuristik.
3. `GenericCsvParser` — beliebiges CSV mit manuellem Spalten-Mapping im UI.
4. `HanseaticPdfParser` — PDF → `pdftotext` → Regex je Layout. **Zuletzt**, weil PDF-Layouts brittle sind.

### Parsing-Realität (ehrlich)
- **CSV/Text sind zuverlässig**, PDF ist es nicht. PDF-Layouts ändern sich, Spalten verrutschen im Textextrakt. Bau PDF erst, wenn du ein echtes Muster deiner Hanseatic-Abrechnung hast — gegen Fantasie-Layouts zu entwickeln ist Zeitverschwendung.
- Deutsche Formate: Datum `DD.MM.YYYY`, Dezimal-Komma `1.234,56`. Eine zentrale `parseGermanAmount()` / `parseGermanDate()` Hilfsfunktion, überall genutzt.
- Encoding: Sparkassen-CSV ist oft `ISO-8859-1`/`Windows-1252`, nicht UTF-8 → beim Einlesen konvertieren, sonst kaputte Umlaute.

### Dedup
`dedup_hash = sha1(account_id + booking_date + amount_cents + normalize(counterparty+description))`.
`normalize()` = lowercase, Mehrfach-Leerzeichen weg, Sonderzeichen weg. Verhindert, dass derselbe Auszug zweimal importiert doppelte Buchungen erzeugt. In der Vorschau werden Treffer als "bereits vorhanden" markiert und standardmäßig nicht übernommen.

---

## 4. Kategorisierung (Regel-Engine)

Beim Import läuft jede Buchung durch die Regeln (`category_rules`), sortiert nach `priority`. Erster Treffer gewinnt. Kein Treffer → Kategorie `Sonstiges` + Flag "ungeprüft".

**Lernen aus Korrekturen:** Wenn du eine Buchung manuell umkategorisierst, bietet das UI an: *"Künftig alle Buchungen von ‚ARAL' als ‚Sprit' einordnen?"* → erzeugt eine `category_rule` mit `auto_created = true`. So wird die Trefferquote mit der Zeit besser, ohne AI.

Start-Regelsatz (Seed) für deine bekannten Fälle: ARAL/Shell/Esso → Sprit, PAYPAL → PayPal, Allianz/HUK/etc. → Versicherung, McFit/Fitness → Gym, Gehalt-Arbeitgeber → Einkommen, Kreditkartenabbuchung → interner Transfer.

---

## 5. Schulden- & Raten-Modell

### Feste Raten (`installment`, z.B. PayPal eBay)
Beim Anlegen wird der komplette `debt_payments`-Plan generiert:
`start_date` + `payment_day`, `installment_count` Einträge, je `monthly_amount`.

Berechnete Werte (Views/Accessor, nicht gespeichert):
- **bezahlt** = Summe `amount` wo `paid = true`
- **offen** = `total_amount` − bezahlt
- **Raten verbleibend** = Anzahl `paid = false`
- **voraussichtlich fertig** = `due_date` der letzten offenen Rate
- **diesen Monat fällig** / **nächsten Monat fällig** = Filter auf `due_date`

Beispiel PayPal eBay (600 €, 6×100 €, ab 21.05.2026): heute (11.06.2026) ist Rate 1 (21.05.) durch, Rate 2 (21.06.) steht an → bezahlt 100 €, offen 500 €, 5 Raten übrig, fertig 21.10.2026.

### Flexible Tilgung (`revolving`, z.B. Kreditkarte)
Kein fester Plan. `debt_payments` werden aus **echten** Zahlungen befüllt (gematcht aus Transaktionen / Statement-Abbuchung). Restschuld = Start − Summe Tilgungen. Bei hinterlegtem Zinssatz optional eine einfache Tilgungsprognose ("bei 50 €/Monat fertig in X Monaten").

### Monatliche Gesamtbelastung
Summe aller `monthly_amount` aktiver `installment`-Schulden + erwartete Tilgung revolvierender Schulden im aktuellen Monat. Fließt direkt in die Budget-Prognose (Abschnitt 6).

### Matching Schuld ↔ echte Buchung
Wenn beim Import eine Buchung am Abbuchungstag mit passendem Betrag/Empfänger auftaucht, schlägt das System vor, sie der fälligen Rate zuzuordnen (`debt_payments.transaction_id`). So bleibt Plan und Realität synchron — kein doppeltes Pflegen.

---

## 6. Einnahmen & Prognose

`incomes` mit `valid_from`/`valid_to` bildet deinen Gehaltssprung ab: altes Netto bis 30.06.2026, neues ab 01.07.2026 (4.000 € brutto). Das Netto fürs neue Gehalt schätzt das System grob oder du trägst es ein, sobald die erste Abrechnung da ist (Netto aus Brutto exakt zu rechnen lohnt im MVP nicht — Steuerklasse, Kirchensteuer etc. sind manuell zuverlässiger).

Abgeleitete Kennzahlen:
- **Verfügbares Budget** = Einkommen − Fixkosten − fällige Raten
- **Fixkostenquote** = Fixkosten / Einkommen
- **Schuldenquote** = fällige Raten / Einkommen
- **Sparpotenzial** = Einkommen − alle erwarteten Ausgaben
- **Kontostand Monatsende (Prognose)** = aktueller Stand + erwarteter Gehaltseingang − ausstehende Fixkosten/Raten bis Monatsende
- **Schuldenfreiheit-Prognose** = spätestes Enddatum über alle aktiven Schulden
- **Auszugs-Prognose** = wann Notgroschen + Kaution + Möbelbudget (Ziele) bei aktueller Sparquote erreicht sind

Prognose-Logik bewusst simpel: erkannte wiederkehrende Buchungen (gleiche `counterparty`, gleicher Rhythmus) + bekannte Raten + erwartetes Gehalt. Keine Statistik-Modelle. Linear in die Zukunft fortgeschrieben, 3–6 Monate. Reicht und ist nachvollziehbar.

---

## 7. Dashboard- & UI-Struktur

### Seiten
1. **Dashboard (Übersicht)** — Startseite. Kontostände, Monat: Einnahmen vs. Ausgaben, Fixkosten vs. variabel, Gesamtschulden, Ziel-Fortschritte, Prognose Monatsende, Warnungen ("Rate fällig am 21.").
2. **Konten** — Liste + Detail mit Transaktionen, Filter, Suche.
3. **Import** — Upload/Paste → Format wählen → Vorschau → bestätigen.
4. **Transaktionen** — globale Liste, Filter (Konto, Kategorie, Zeitraum), Massen-Umkategorisierung.
5. **Kreditkarte** — Statement-Analyse: alter/neuer Saldo, Zinsen, Limit/verfügbar, Umsätze nach Kategorie, Vormonatsvergleich.
6. **Schulden & Raten** — Schuldenliste, Ratenplan, Fälligkeiten, Gesamtbelastung.
7. **Ziele** — Ziel-Karten mit Fortschrittsbalken.
8. **Statistiken** — alle Graphen (Abschnitt 8).
9. **Einstellungen** — Kategorien & Regeln verwalten, Einkommen pflegen, Backup/Export.

### Dashboard-Widgets (oben = wichtigstes)
Kontostände-Zeile · "Diesen Monat" (Einnahmen/Ausgaben/Saldo) · Prognose Monatsende · fällige Raten nächste 14 Tage · Gesamtschulden + Trend · Top-5-Ausgabenkategorien · Ziel-Fortschritt.

---

## 8. Graphen-Modell

Jeder Graph = eine Query, die Buchungen aggregiert. Chart.js rendert. Geplant:

| Graph | Typ | Datenquelle |
|---|---|---|
| Einnahmen vs. Ausgaben (Monat) | gruppierte Balken | transactions nach Monat/type |
| Fixkosten vs. variabel | Balken/Donut | category-Flag fix/variabel |
| Schuldenverlauf | Linie | debts Restschuld je Monat |
| Kreditkartensaldo-Verlauf | Linie | credit_card_statements.new_balance |
| PayPal-Restschuld-Verlauf | Linie | debt (PayPal) Restschuld |
| Ausgaben nach Kategorie | Donut | transactions Monat nach category |
| Spritkosten pro Monat | Balken | category = Sprit |
| Auto-Gesamtkosten | gestapelt | parent-category Auto (Sprit+Versicherung+...) |
| Sparquote | Linie | (Einkommen − Ausgaben)/Einkommen je Monat |
| Monatsvergleich | gruppierte Balken | aktuell vs. Vormonat je Kategorie |
| Prognose 3–6 Monate | Linie (gestrichelt) | Forecast-Logik Abschnitt 6 |

Markiere Kategorien mit einem `is_fixed`-Flag (Versicherung, Gym, Wohnen, Altersvorsorge = fix; Sprit, Essen, Freizeit = variabel) — das speist mehrere Graphen ohne Extra-Logik.

---

## 9. Edge Cases bei Finanzdaten (von Anfang an mitdenken)

- **Interne Transfers nicht doppelt zählen.** Giro bucht −X an Kreditkarte, die Karte zeigt Zahlungseingang +X. Beides als Ausgabe zu zählen verfälscht alles. → `is_internal_transfer`-Flag, Transfers fließen nicht in "Ausgaben" der Auswertungen ein.
- **Erstattungen/Gutschriften** = negative Ausgabe (Geld zurück). Korrekt als positiver Betrag in der Ausgabenkategorie, nicht als "Einkommen".
- **Geld immer als Integer-Cent**, nie Float.
- **Datums-/Zahlenformat** deutsch, zentral parsen. Zeitzone Europe/Berlin fix.
- **Doppelimport** via dedup_hash abfangen.
- **Teilmonate**: laufender Monat ist unvollständig → Prognosen kennzeichnen ("Hochrechnung").
- **Gehalt "Ende Monat"** ist mal der 28., mal der letzte Werktag → payday flexibel, Prognose toleriert ±wenige Tage.
- **Kategorie nachträglich ändern** darf alte Auswertungen rückwirkend korrigieren (keine eingefrorenen Snapshots im MVP).

---

## 10. Entwicklungsreihenfolge (Phasen)

Jede Phase ist für sich nutzbar — du hast nach Phase 1 schon ein funktionierendes Tool.

**Phase 0 — Skeleton.** Docker + Laravel + SQLite, Migrations für `accounts`, `categories`, `transactions`. Seed der Standardkategorien.

**Phase 1 — Manuell nutzbar.** Konten anlegen, Buchungen manuell erfassen, Transaktionsliste, Kontostand, simples Dashboard (Einnahmen/Ausgaben Monat). → *Ab hier real einsetzbar.*

**Phase 2 — Import.** `SparkasseCsvParser` + Import-Vorschau + Dedup. Danach `GenericTextParser` (Copy-Paste).

**Phase 3 — Kategorisierung.** Regel-Engine, manuelle Korrektur, Lernen aus Korrekturen, Seed-Regeln.

**Phase 4 — Schulden & Raten.** `debts` + `debt_payments`, Ratenplan-Generierung, Fälligkeiten, Gesamtbelastung. (Deckt deine zwei konkreten Schulden ab.)

**Phase 5 — Kreditkarte.** `HanseaticPdfParser` (mit echtem Muster) + Statement-Analyse-Seite.

**Phase 6 — Graphen.** Chart.js, die Diagramme aus Abschnitt 8.

**Phase 7 — Einnahmen & Prognose.** Income-Modell, Budget-/Kontostand-/Schuldenfreiheit-Prognose.

**Phase 8 — Ziele.** Ziel-Tracking mit Fortschritt, Verknüpfung zu Schulden/Konten.

**Später (optional):** Export (CSV/PDF), automatisches Backup der SQLite-Datei, Mobile-Ansicht, AI-Fallback fürs Kategorisieren.

---

## Nächster Schritt

Vorschlag: Ich baue **Phase 0 + 1** als lauffähiges Gerüst — Docker-Setup, Migrations, Konten + manuelle Buchungen + Basis-Dashboard. Dann hast du sofort etwas zum Anfassen und wir iterieren von dort.

Sag Bescheid, ob die Stack-Wahl und die Phasen für dich passen, oder ob du an einer Stelle (z.B. PDF früher, Auth doch rein) umpriorisieren willst.
