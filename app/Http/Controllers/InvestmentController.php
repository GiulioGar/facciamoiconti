<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Investment;
use App\Models\FinancialBalance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class InvestmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store multiple investments and append a new FinancialBalance snapshot.
     */
    public function store(Request $request)
    {
        // 1) Validazione inclusa accounting_month
        $data = $request->validate([
            'family_id'                        => 'required|exists:families,id',
            'accounting_month'                 => 'required|date_format:Y-m',
            'investments'                      => 'required|array',
            'investments.*.category_id'        => 'required|exists:investment_categories,id',
            'investments.*.current_balance'    => 'required|numeric|min:0',
            'investments.*.invested_balance'   => 'required|numeric|min:0',
        ]);

        if (! Auth::user()->belongsToFamily((int) $data['family_id'])) {
            abort(403, 'Non sei membro di questa famiglia.');
        }

        // 2) Converto YYYY-MM → YYYY-MM-01
        $periodDate = Carbon::createFromFormat('Y-m', $data['accounting_month'])
                        ->startOfMonth()
                        ->toDateString();

        // 3) Creo i record in investments
        foreach ($data['investments'] as $inv) {
            Investment::create([
                'user_id'          => Auth::id(),
                'family_id'        => $data['family_id'],
                'category_id'      => $inv['category_id'],
                'current_balance'  => $inv['current_balance'],
                'invested_balance' => $inv['invested_balance'],
            ]);
        }

        // 4) Calcolo il totale di tutti i "Saldo" (current_balance) dalla modale
        $newInvestedTotal = array_sum(array_column($data['investments'], 'current_balance'));

        // 5) Aggiorna solo il campo investments per il mese selezionato (upsert)
        FinancialBalance::updateOrCreate(
            [
                'user_id'          => Auth::id(),
                'family_id'        => (int) $data['family_id'],
                'accounting_month' => $periodDate,
            ],
            [
                'investments' => $newInvestedTotal,
            ]
        );

        // 9) Redirect con messaggio di successo
        return back()->with('success', 'Investimenti salvati e snapshot aggiornato correttamente');
    }
}
