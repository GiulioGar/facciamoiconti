<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FinancialBalanceController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvestmentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function() {
    // Dashboard unica per tutti i ruoli
    Route::get('dashboard', [AdminController::class, 'dashboard'])
         ->name('dashboard');
});

Route::middleware('auth')->group(function() {

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


    // Solo capofamiglia può creare e gestire richieste
    Route::middleware('check.role:capofamiglia')->group(function() {
        Route::get('families/create', [FamilyController::class, 'create'])
             ->name('families.create');
        Route::post('families', [FamilyController::class, 'store'])
             ->name('families.store');
        Route::get('families/{family}/requests', [FamilyController::class, 'requests'])
             ->name('families.requests');
        Route::post('families/{family}/requests/{user}/respond', [FamilyController::class, 'respond'])
             ->name('families.respond');
    });

    // Tutti gli utenti autenticati vedono la lista famiglie e possono richiedere di entrare
    Route::get('families', [FamilyController::class, 'index'])
         ->name('families.index');
    Route::post('families/{family}/join', [FamilyController::class, 'join'])
         ->name('families.join');


Route::post('/balance', [FinancialBalanceController::class, 'store'])->name('balance.store');

    // Entrate
    Route::get('incomes', [IncomeController::class, 'index'])
         ->name('incomes.index');

    Route::get('incomes/create', [IncomeController::class, 'create'])
         ->name('incomes.create');

    Route::post('incomes', [IncomeController::class, 'store'])
         ->name('incomes.store');

       // Rotte per le uscite
    Route::resource('expenses', ExpenseController::class)
         ->except(['show']); // puoi togliere show se non ti serve

        Route::post('/investments', [InvestmentController::class, 'store'])
            ->name('investments.store')
            ->middleware('auth');



// Qualunque altra rotta (non definita) → redirect a login
 Route::fallback(function () {
 return redirect()->route('login');
 });







});

