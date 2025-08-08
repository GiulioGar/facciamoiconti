<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;
use App\Models\IncomeAllocation;
use App\Models\BudgetCategory;
use Carbon\Carbon;
use App\Models\Family;
use Illuminate\Support\Facades\DB;
use App\Models\FinancialBalance;

class IncomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show form per creare una nuova entrata
     */
    public function create()
    {
        $user   = Auth::user();
        $family = $user->families()->wherePivot('status','accepted')->first();
        $categories = BudgetCategory::orderBy('sort_order')->get();

        return view('incomes.create', compact('family','categories'));
    }



public function store(Request $request)
{
    $data = $request->validate([
        'description'       => 'required|string|max:255',
        'amount'            => 'required|numeric|min:0.01',
        'date'              => 'required|date',
        'allocations.*'     => 'nullable|numeric|min:0',
        'family_id'         => 'required|exists:families,id',
        'wallet_allocation' => 'required|in:bank,cash,none',
    ]);

    DB::transaction(function() use ($data) {

        // 1) Crea l'entrata
        $income = Income::create([
            'description' => $data['description'],
            'amount'      => $data['amount'],
            'date'        => $data['date'],
            'family_id'   => $data['family_id'],
            'user_id'     => Auth::id(),
        ]);

        // 2) Allocazioni budget (come già facevi)
        $typeMap = BudgetCategory::pluck('slug', 'id')->toArray();

        if (!empty($data['allocations'])) {
            foreach ($data['allocations'] as $categoryId => $value) {
                if ($value > 0) {
                    $income->allocations()->create([
                        'category_id' => $categoryId,
                        'amount'      => $value,
                        'type'        => $typeMap[$categoryId] ?? 'category',
                    ]);
                }
            }
        }

        // 3) Scrivi una NUOVA riga in financial_balances se richiesto
        if ($data['wallet_allocation'] !== 'none') {

            $userId   = Auth::id();
            $familyId = (int) $data['family_id'];
            $amount   = (float) $data['amount'];

            // Ultima riga per user+family
            $last = FinancialBalance::where('user_id', $userId)
                    ->where('family_id', $familyId)
                    ->orderByDesc('id')
                    ->first();

            // Base: se non esiste nulla, parti da 0
            $base = [
                'bank_balance'   => (float) ($last->bank_balance   ?? 0),
                'other_accounts' => (float) ($last->other_accounts ?? 0),
                'cash'           => (float) ($last->cash           ?? 0),
                'insurances'     => (float) ($last->insurances     ?? 0),
                'investments'    => (float) ($last->investments    ?? 0),
                'debt_credit'    => (float) ($last->debt_credit    ?? 0),
            ];

            // Colonna target in base alla scelta
            $columnMap = [
                'bank' => 'bank_balance',
                'cash' => 'cash',
            ];
            $targetCol = $columnMap[$data['wallet_allocation']] ?? null;

            if ($targetCol) {
                $base[$targetCol] = round($base[$targetCol] + $amount, 2);
            }

            // accounting_month = primo giorno del mese dell'entrata
            $accountingMonth = \Carbon\Carbon::parse($data['date'])->startOfMonth()->toDateString();

            FinancialBalance::create(array_merge($base, [
                'user_id'         => $userId,
                'family_id'       => $familyId,
                'accounting_month'=> $accountingMonth,
            ]));
        }
    });

    return back()->with('success', 'Entrata aggiunta con successo');
}


  public function index()
{
    $user = Auth::user();

    // 1) Trova la famiglia corrente:
    //    - se è owner, la prende da owner_id
    //    - altrimenti la prende da pivot members con status = accepted
    $family = Family::where('owner_id', $user->id)
        ->orWhereHas('members', function($q) use($user) {
            $q->where('user_id', $user->id)
              ->where('status', 'accepted');
        })
        ->first();

    // 2) Se per qualche motivo ancora non esiste, gestisci il fallback
    if (! $family) {
        // ad esempio: rendi la collection di entrate vuota e mostra un avviso
        $incomes    = collect();
        $categories = BudgetCategory::orderBy('sort_order')->get();

        return view('incomes.index', compact('incomes','categories'))
               ->with('noFamily', true);
    }

    // 3) A questo punto $family è un oggetto valido e puoi usare $family->id
    $incomes = Income::with('allocations.category')
                     ->where('user_id', $user->id)
                     ->where('family_id', $family->id)
                     ->orderBy('date','desc')
                     ->orderBy('id',   'desc')
                     ->paginate(20);

    $categories = BudgetCategory::orderBy('sort_order')->get();

    return view('incomes.index', compact(
        'incomes','family','categories'
    ));
}
}
