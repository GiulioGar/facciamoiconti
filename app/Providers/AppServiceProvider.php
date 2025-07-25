<?php

namespace App\Providers;
use Carbon\Carbon;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    // Imposta la lingua italiana per Carbon
    Carbon::setLocale('it');

    // Assicurati che il sistema abbia installato l'Italiano (it_IT)
    setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'it');
    }
}
