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
        // 1) Regole di validazione dinamiche
        $rules = [
            'family_id'        => 'required|exists:families,id',
            'accounting_month' => 'required|date_format:Y-m',
        ];

        $fields = [
            'bank_balance','other_accounts','cash',
            'insurances','investments','debt_credit',
        ];
        foreach ($fields as $f) {
            if ($request->has($f)) {
                $rules[$f] = 'required|numeric';
            }
        }

        $validated = $request->validate($rules);

        // 2) Periodo contabile
        $period = Carbon::createFromFormat('Y-m', $validated['accounting_month'])
                        ->startOfMonth();

        // 3) Prendi l’ultimo snapshot (qualsiasi mese) per user/family
        $latest = FinancialBalance::where('user_id', Auth::id())
                  ->where('family_id', $validated['family_id'])
                  ->orderBy('accounting_month', 'desc')
                  ->first();

        // 4) Prepara i dati base: se esiste uno snapshot, eredita i suoi valori, altrimenti 0
        $data = [
            'user_id'          => Auth::id(),
            'family_id'        => $validated['family_id'],
            'accounting_month' => $period,
            'bank_balance'     => $latest ? $latest->bank_balance   : 0,
            'other_accounts'   => $latest ? $latest->other_accounts : 0,
            'cash'             => $latest ? $latest->cash           : 0,
            'insurances'       => $latest ? $latest->insurances     : 0,
            'investments'      => $latest ? $latest->investments    : 0,
            'debt_credit'      => $latest ? $latest->debt_credit     : 0,
        ];

        // 5) Sovrascrivi solo il campo che arriva dal form
        foreach ($fields as $f) {
            if (array_key_exists($f, $validated)) {
                $data[$f] = $validated[$f];
            }
        }

        // 6) Crea sempre un nuovo snapshot
        FinancialBalance::create($data);

        // 7) Redirect senza flash di “success”
        return redirect()->route('home');
    }
}
