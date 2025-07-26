<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Family;
use App\Models\FinancialBalance;
use App\Models\BudgetCategory;
use App\Models\IncomeAllocation;
use App\Models\Income;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1) Periodo contabile (primo giorno del mese)
        if ($request->filled('accounting_month')) {
            $period = Carbon::createFromFormat('Y-m', $request->input('accounting_month'))
                            ->startOfMonth();
        } else {
            $period = Carbon::now()->startOfMonth();
        }
        $periodDate   = $period->toDateString();
        $periodString = $period->format('Y-m');

        // 2) Trova la famiglia
        if ($user->role === 'capofamiglia') {
            $ownFamilies     = Family::where('owner_id', $user->id)->get();
            $family          = $ownFamilies->first();
            $pendingRequests = $user->ownedFamilies()
                                    ->with(['members' => function($q) {
                                        $q->wherePivot('status','pending');
                                    }])
                                    ->get()
                                    ->flatMap->members;
        } else {
            $families = Family::withCount(['members' => function($q) {
                                $q->where('status','accepted');
                            }])->get();
            $family   = $user->families()
                             ->wherePivot('status','accepted')
                             ->first();
        }

        // 3) Lista 36 mesi per i modali
        $balanceMonths = [];
        $cursor = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 36; $i++) {
            $balanceMonths[] = $cursor->copy()->subMonths($i)->format('Y-m');
        }

        // 4) Snapshot per il mese selezionato
        if ($family) {
            $balance = FinancialBalance::where('user_id', $user->id)
                ->where('family_id', $family->id)
                ->where('accounting_month', $periodDate)
                ->orderBy('id', 'desc')
                ->first();

            $hasBalance = (bool) $balance;
            if (! $balance) {
                $balance = new FinancialBalance([
                    'user_id'          => $user->id,
                    'family_id'        => $family->id,
                    'bank_balance'     => 0,
                    'other_accounts'   => 0,
                    'cash'             => 0,
                    'insurances'       => 0,
                    'investments'      => 0,
                    'debt_credit'      => 0,
                    'accounting_month' => $periodDate,
                ]);
            }
        } else {
            $hasBalance = false;
            $balance = new FinancialBalance([
                'user_id'          => $user->id,
                'family_id'        => null,
                'bank_balance'     => 0,
                'other_accounts'   => 0,
                'cash'             => 0,
                'insurances'       => 0,
                'investments'      => 0,
                'debt_credit'      => 0,
                'accounting_month' => $periodDate,
            ]);
        }

        // 5) Calcoli saldi
        $total  = $balance->bank_balance
                 + $balance->other_accounts
                 + $balance->cash
                 + $balance->insurances
                 + $balance->investments
                 + $balance->debt_credit;
        $liquid = $balance->bank_balance
                 + $balance->other_accounts
                 + $balance->cash;

        // 6) Ultimo snapshot globale
        $latestMonth = FinancialBalance::where('user_id',   $user->id)
            ->where('family_id', $family->id ?? 0)
            ->max('accounting_month');

        if ($latestMonth) {
            $latestBalance = FinancialBalance::where('user_id', $user->id)
                ->where('family_id', $family->id ?? 0)
                ->where('accounting_month', $latestMonth)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $latestBalance = null;
        }

        $latestTotal  = $latestBalance ? (
                          $latestBalance->bank_balance
                        + $latestBalance->other_accounts
                        + $latestBalance->cash
                        + $latestBalance->insurances
                        + $latestBalance->investments
                        + $latestBalance->debt_credit
                        ) : 0;
        $latestLiquid = $latestBalance ? (
                          $latestBalance->bank_balance
                        + $latestBalance->other_accounts
                        + $latestBalance->cash
                        ) : 0;

        // 7) Carica categorie per budget mensile
        $categories = BudgetCategory::orderBy('sort_order')->get();

        // 8) Calcolo entrate/uscite mensili per categoria
        $incomeByCategory  = [];
        $expenseByCategory = [];

        foreach ($categories as $cat) {
            // Entrate: somma di allocazioni (IncomeAllocation)
            $incomeByCategory[$cat->id] = DB::table('income_allocations as ia')
                ->join('incomes as i', 'ia.income_id', '=', 'i.id')
                ->select(DB::raw('MONTH(i.date) as month'), DB::raw('SUM(ia.amount) as total'))
                ->where('ia.category_id', $cat->id)
                ->where('i.user_id', $user->id)
                ->where('i.family_id', $family->id ?? null)
                ->groupBy('month')
                ->pluck('total','month')
                ->toArray();

            // Uscite: somma di expenses per budget_category_id
            $expenseByCategory[$cat->id] = DB::table('expenses')
                ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
                ->where('budget_category_id', $cat->id)
                ->where('user_id', $user->id)
                ->where('family_id', $family->id ?? null)
                ->groupBy('month')
                ->pluck('total','month')
                ->toArray();
        }

            // ======================================
            // 9) Totale budget per categoria
            //    = start_amount + entrate - uscite
            // ======================================
            $budgetTotalByCategory = [];
            foreach ($categories as $cat) {
                $sumInc = array_sum($incomeByCategory[$cat->id]  ?? []);
                $sumExp = array_sum($expenseByCategory[$cat->id] ?? []);
                $budgetTotalByCategory[$cat->id] = $cat->start_amount + $sumInc - $sumExp;
            }

        // 9) Render della view con dati puliti
        return view('home', [
            'ownFamilies'        => $ownFamilies      ?? null,
            'pendingRequests'    => $pendingRequests  ?? null,
            'families'           => $families         ?? null,
            'family'             => $family           ?? null,
            'balance'            => $balance,
            'total'              => $total,
            'liquid'             => $liquid,
            'period'             => $periodString,
            'hasBalance'         => $hasBalance,
            'balanceMonths'      => $balanceMonths,
            'latestBalance'      => $latestBalance,
            'latestTotal'        => $latestTotal,
            'latestLiquid'       => $latestLiquid,
            'categories'         => $categories,
            'incomeByCategory'   => $incomeByCategory,
            'expenseByCategory'  => $expenseByCategory,
            'budgetTotalByCategory'   => $budgetTotalByCategory,
        ]);
    }
}
