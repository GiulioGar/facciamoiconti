<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FinancialBalance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FinancialBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

public function store(Request $request)
{
    // 1) Validation: tutti i campi obbligatori
    $validated = $request->validate([
        'family_id'         => 'required|exists:families,id',
        'accounting_month'  => 'required|date_format:Y-m',
        'bank_balance'      => 'required|numeric|min:0',
        'other_accounts'    => 'required|numeric|min:0',
        'cash'              => 'required|numeric|min:0',
        'insurances'        => 'required|numeric|min:0',
        'investments'       => 'required|numeric|min:0',
        'debt_credit'       => 'required|numeric|min:0',
    ]);

    // 2) Converti 'YYYY-MM' → 'YYYY-MM-01'
    $periodDate = Carbon::createFromFormat('Y-m', $validated['accounting_month'])
                    ->startOfMonth()
                    ->toDateString();

    // 3) Prepara i dati: eredita ultimo snapshot se serve (opzionale)
    $latest = FinancialBalance::where('user_id', Auth::id())
              ->where('family_id', $validated['family_id'])
              ->orderBy('accounting_month','desc')
              ->first();

    $data = [
        'user_id'          => Auth::id(),
        'family_id'        => $validated['family_id'],
        'accounting_month' => $periodDate,
        'bank_balance'     => $validated['bank_balance'],
        'other_accounts'   => $validated['other_accounts'],
        'cash'             => $validated['cash'],
        'insurances'       => $validated['insurances'],
        'investments'      => $validated['investments'],
        'debt_credit'      => $validated['debt_credit'],
    ];

    // 4) Crea SEMPRE un nuovo snapshot (nuova riga)
    FinancialBalance::create($data);

    // 5) Redirect con feedback
    return redirect()
           ->route('home')
           ->with('success', 'Suddivisione Attività salvata correttamente');
}

}
