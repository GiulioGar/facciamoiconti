<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // Comandi che in produzione potrebbero azzerare l'intero database.
    private const BLOCKED_IN_PRODUCTION = [
        'migrate:fresh',
        'migrate:rollback',
        'migrate:reset',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('it');
        setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'it');

        // Le migrazioni fantacalcio vivono in una sottocartella separata
        // e vengono sempre incluse nel ciclo migrate normale.
        $this->loadMigrationsFrom(database_path('migrations/fantacalcio'));

        // In produzione blocca i comandi che distruggono l'intero schema.
        // migrate:fresh / rollback / reset non devono mai girare su dati reali.
        if ($this->app->isProduction()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event) {
                if (in_array($event->command, self::BLOCKED_IN_PRODUCTION, true)) {
                    fwrite(STDERR, implode("\n", [
                        '',
                        '╔══════════════════════════════════════════════════════╗',
                        '║  COMANDO BLOCCATO IN PRODUZIONE                      ║',
                        "║  '{$event->command}' può azzerare l'intero database. ║",
                        '║  Eseguire solo migrate (senza opzioni distruttive).  ║',
                        '╚══════════════════════════════════════════════════════╝',
                        '',
                    ]) . "\n");
                    exit(1);
                }
            });
        }
    }
}
