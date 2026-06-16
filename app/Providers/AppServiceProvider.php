<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SQLite-Tuning, BIND-MOUNT-SICHER.
        // WICHTIG: KEIN journal_mode=WAL! WAL braucht Shared-Memory-Dateien
        // (-wal/-shm), die auf Docker-Bind-Mounts unter Windows oft nicht
        // korrekt funktionieren -> Schreibfehler/Rollback beim Import-Commit.
        // synchronous=NORMAL (mit Default-Journal) ist schnell genug und sicher,
        // busy_timeout fängt kurze Sperren ab.
        if (config('database.default') === 'sqlite') {
            try {
                // Aktiv auf Rollback-Journal zurücksetzen: journal_mode ist in der
                // DB-Datei persistent, eine zuvor auf WAL gestellte Datei bliebe
                // sonst im (bind-mount-unverträglichen) WAL-Modus.
                DB::statement('PRAGMA journal_mode=DELETE');
                DB::statement('PRAGMA synchronous=NORMAL');
                DB::statement('PRAGMA busy_timeout=5000');
            } catch (\Throwable $e) {
                // Im Zweifel lieber ohne Tuning starten als crashen.
            }
        }
    }
}
