<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FinancialBalance;
use App\Models\WalletMovement;
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
        $validated = $request->validate([
            'family_id'        => 'required|exists:families,id',
            'accounting_month' => 'required|date_format:Y-m',
            'bank_balance'     => 'required|numeric|min:0',
            'other_accounts'   => 'required|numeric|min:0',
            'cash'             => 'required|numeric|min:0',
            'insurances'       => 'required|numeric|min:0',
            'investments'      => 'required|numeric|min:0',
            'debt_credit'      => 'required|numeric|min:0',
        ]);

        if (! Auth::user()->belongsToFamily((int) $validated['family_id'])) {
            abort(403, 'Non sei membro di questa famiglia.');
        }

        $userId   = Auth::id();
        $familyId = (int) $validated['family_id'];

        $periodDate = Carbon::createFromFormat('Y-m', $validated['accounting_month'])
                        ->startOfMonth()
                        ->toDateString();

        // --- Bank e Cash: riconciliazione tramite WalletMovement ---
        // Il saldo reale è la somma di tutti i movimenti wallet.
        // Se l'utente inserisce un valore diverso, creiamo un movimento
        // di rettifica (adjustment) per colmare la differenza.

        $computedBank = (float) WalletMovement::where('user_id', $userId)
            ->where('family_id', $familyId)
            ->where('account', 'bank')
            ->sum('amount');

        $computedCash = (float) WalletMovement::where('user_id', $userId)
            ->where('family_id', $familyId)
            ->where('account', 'cash')
            ->sum('amount');

        $deltaBank = round((float) $validated['bank_balance'] - $computedBank, 2);
        $deltaCash = round((float) $validated['cash'] - $computedCash, 2);

        if (abs($deltaBank) > 0.001) {
            WalletMovement::create([
                'user_id'     => $userId,
                'family_id'   => $familyId,
                'account'     => 'bank',
                'amount'      => $deltaBank,
                'source_type' => 'adjustment',
                'source_id'   => null,
                'date'        => $periodDate,
                'note'        => 'Riconciliazione manuale',
            ]);
        }

        if (abs($deltaCash) > 0.001) {
            WalletMovement::create([
                'user_id'     => $userId,
                'family_id'   => $familyId,
                'account'     => 'cash',
                'amount'      => $deltaCash,
                'source_type' => 'adjustment',
                'source_id'   => null,
                'date'        => $periodDate,
                'note'        => 'Riconciliazione manuale',
            ]);
        }

        // --- Altri campi: un solo record per mese (upsert) ---
        FinancialBalance::updateOrCreate(
            [
                'user_id'          => $userId,
                'family_id'        => $familyId,
                'accounting_month' => $periodDate,
            ],
            [
                'bank_balance'   => $validated['bank_balance'],
                'other_accounts' => $validated['other_accounts'],
                'cash'           => $validated['cash'],
                'insurances'     => $validated['insurances'],
                'investments'    => $validated['investments'],
                'debt_credit'    => $validated['debt_credit'],
            ]
        );

        return redirect()
               ->route('home')
               ->with('success', 'Suddivisione Attività salvata correttamente');
    }
}
