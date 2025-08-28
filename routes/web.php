<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FinancialBalanceController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\FantacalcioController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Auth::routes();

/**
 * Redirect root -> /home
 */
Route::get('/', fn () => redirect()->route('home'));

/**
 * Area Admin (solo autenticati)
 */
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
});

/**
 * Rotte protette (autenticazione obbligatoria)
 */
Route::middleware('auth')->group(function () {

    // Home
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Solo capofamiglia: crea/gestisci richieste
    Route::middleware('check.role:capofamiglia')->group(function () {
        Route::get('families/create', [FamilyController::class, 'create'])->name('families.create');
        Route::post('families', [FamilyController::class, 'store'])->name('families.store');
        Route::get('families/{family}/requests', [FamilyController::class, 'requests'])->name('families.requests');
        Route::post('families/{family}/requests/{user}/respond', [FamilyController::class, 'respond'])->name('families.respond');
    });

    // Famiglie (tutti i loggati)
    Route::get('families', [FamilyController::class, 'index'])->name('families.index');
    Route::post('families/{family}/join', [FamilyController::class, 'join'])->name('families.join');

    // Financial balances
    Route::post('/balance', [FinancialBalanceController::class, 'store'])->name('balance.store');

    // Entrate
    Route::get('incomes', [IncomeController::class, 'index'])->name('incomes.index');
    Route::get('incomes/create', [IncomeController::class, 'create'])->name('incomes.create');
    Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');

    // Uscite
    Route::resource('expenses', ExpenseController::class)->except(['show']);

    // Conti uniti per famiglia
    Route::get(
        'families/{family}/combined-balances',
        [FamilyController::class, 'combinedBalances']
    )->name('families.combined-balances');

    // Investimenti
    Route::post('/investments', [InvestmentController::class, 'store'])->name('investments.store');

    /**
     * ===========================
     *        FANTACALCIO
     * ===========================
     *
     * Nomi coerenti (fantacalcio.*), id vincolati numerici per le POST,
     * tutto dietro auth.
     */
    Route::prefix('fantacalcio')->as('fantacalcio.')->group(function () {

        // Dashboard Fantacalcio
        Route::get('/', [FantacalcioController::class, 'index'])->name('index');

        // Quote & import
        Route::get('/quote', [FantacalcioController::class, 'quote'])->name('quote');
        Route::post('/quote/import', [FantacalcioController::class, 'quoteImport'])->name('quote.import');

        // Sync listone
        Route::post('/listone/sync', [FantacalcioController::class, 'listoneSync'])->name('listone.sync');

        // DataTables (GET JSON)
        Route::get('/listone/data', [FantacalcioController::class, 'listoneData'])->name('listone.data');

        // Rosa
        Route::get('/rosa', [FantacalcioController::class, 'rosa'])->name('rosa');
        Route::get('/rosa/players', [FantacalcioController::class, 'rosaPlayers'])->name('rosa.players');

        // Aggiungi / rimuovi dalla rosa
        Route::post('/rosa/add',    [FantacalcioController::class, 'rosaAdd'])->name('rosa.add');
        Route::post('/rosa/remove', [FantacalcioController::class, 'rosaRemove'])->name('rosa.remove');

        // Azioni su calciatore (vincolo id numerico)
        Route::post('/player/{id}/like',         [FantacalcioController::class, 'incrementLike'])
            ->whereNumber('id')->name('player.like');

        Route::post('/player/{id}/dislike',      [FantacalcioController::class, 'incrementDislike'])
            ->whereNumber('id')->name('player.dislike');

        Route::post('/player/{id}/toggle-stato', [FantacalcioController::class, 'toggleStato'])
            ->whereNumber('id')->name('player.toggleStato');

        Route::post('/player/{id}/like/dec',     [FantacalcioController::class, 'decrementLike'])
            ->whereNumber('id')->name('player.like.dec');

        Route::post('/player/{id}/dislike/dec',  [FantacalcioController::class, 'decrementDislike'])
            ->whereNumber('id')->name('player.dislike.dec');
    });
});

/**
 * Fallback: se autenticato → home, altrimenti → login
 * (tenuto fuori dal gruppo auth per evitare redirect strani)
 */
Route::fallback(function () {
    return auth()->check()
        ? redirect()->route('home')
        : redirect()->route('login');
});
