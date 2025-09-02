<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // === aggiunto ===
use App\Services\FamilyBudgetSummary;   // === aggiunto ===
use App\Models\Family;
use App\Models\FinancialBalance;
use App\Models\BudgetCategory;
use App\Models\IncomeAllocation;
use App\Models\Income;
use App\Models\Investment;
use App\Models\InvestmentCategory;
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
        $currentYear = Carbon::now()->year;

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

        // === Riepilogo centralizzato (Service + cache JSON) ===
        // Questi 5 valori saranno disponibili sia in home sia per la pagina families/summary (via JSON)
        $familiare = $extra = $risparmi = $personale = $totale = 0.0;
        if ($family) {
            // Calcola UNA volta via Service (start_amount globale + allocazioni famiglia – uscite famiglia)
            $familySummary = FamilyBudgetSummary::build($user->id, $family->id);

            $familiare = $familySummary['familiare'];
            $extra     = $familySummary['extra'];
            $risparmi  = $familySummary['risparmi'];
            $personale = $familySummary['personale'];
            $totale    = $familySummary['totale'];

            // (Opzionale) Cache JSON: storage/app/families/{id}/summary.json
            $cachePath = "families/{$family->id}/summary.json";
            Storage::disk('local')->put($cachePath, json_encode([
                'generated_at' => now()->toDateTimeString(),
                'data' => $familySummary,
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        // === fine blocco riepilogo ===

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
        foreach ($categories as $cat) {
            // *******************
            // ENTRATE (solo anno corrente)
            // *******************
            $incomeByCategory[$cat->id] = DB::table('income_allocations as ia')
                ->join('incomes as i', 'ia.income_id', '=', 'i.id')
                ->select(
                    DB::raw('MONTH(i.date) as month'),
                    DB::raw('SUM(ia.amount) as total')
                )
                ->where('ia.category_id', $cat->id)
                ->where('i.user_id',   $user->id)
                ->where('i.family_id', $family->id)
                ->whereYear('i.date',   $currentYear)      // <— Filtro anno
                ->groupBy('month')
                ->pluck('total','month')
                ->toArray();

            // *******************
            // USCITE (solo anno corrente)
            // *******************
            $expenseByCategory[$cat->id] = DB::table('expenses')
                ->select(
                    DB::raw('MONTH(date) as month'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('budget_category_id', $cat->id)
                ->where('user_id',   $user->id)
                ->where('family_id', $family->id)
                ->whereYear('date',    $currentYear)      // <— Filtro anno
                ->groupBy('month')
                ->pluck('total','month')
                ->toArray();
        }

        // Somma **tutti** gli anni (entrate)
        $totalIncomeAllYears   = [];
        // Somma **tutti** gli anni (uscite)
        $totalExpenseAllYears  = [];

        foreach ($categories as $cat) {
            $totalIncomeAllYears[$cat->id] = DB::table('income_allocations as ia')
                ->join('incomes as i', 'ia.income_id', '=', 'i.id')
                ->where('ia.category_id',    $cat->id)
                ->where('i.user_id',         $user->id)
                ->where('i.family_id',       $family->id)
                ->sum('ia.amount');

            $totalExpenseAllYears[$cat->id] = DB::table('expenses')
                ->where('budget_category_id', $cat->id)
                ->where('user_id',            $user->id)
                ->where('family_id',          $family->id)
                ->sum('amount');
        }

        // ======================================
        // 9) Totale budget per categoria
        //    = start_amount + entrate - uscite
        // ======================================
        $budgetTotalByCategory = [];
        foreach ($categories as $cat) {
            $sumIncAll = $totalIncomeAllYears[$cat->id]  ?? 0;
            $sumExpAll = $totalExpenseAllYears[$cat->id] ?? 0;
            $start     = (Auth::id() === 1) ? $cat->start_amount : 0;

            $budgetTotalByCategory[$cat->id] = $start + $sumIncAll - $sumExpAll;
        }

        // Calcolo del totale assegnato a tutti i budget
        $assignedTotal = array_sum($budgetTotalByCategory);

        // ————————————————————————
        // Resoconto Mensile Totale
        // ————————————————————————
        $summaryIncomeByMonth = DB::table('incomes')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', auth()->id())
            ->whereYear('date',    $currentYear)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        $summaryExpenseByMonth = DB::table('expenses')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', auth()->id())
            ->whereYear('date',    $currentYear)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        // Calcolo il “Risultato” mese per mese (solo interi)
        $summaryGainByMonth = [];
        for($m = 1; $m <= 12; $m++){
            $inc  = intval(round($summaryIncomeByMonth[$m]  ?? 0));
            $exp  = intval(round($summaryExpenseByMonth[$m] ?? 0));
            $summaryGainByMonth[$m] = $inc - $exp;
        }

        // Totale dell’anno
        $totalSummaryIncome  = array_sum(array_map('intval', $summaryIncomeByMonth));
        $totalSummaryExpense = array_sum(array_map('intval', $summaryExpenseByMonth));
        $totalSummaryGain    = array_sum($summaryGainByMonth);

        // ——————————————————————————————
        // Investimenti: carica categorie e ultimi valori
        // ——————————————————————————————
        $investmentCategories = InvestmentCategory::orderBy('name')->get();

        $latestInvestments = [];
        $investmentSummary = [];

        foreach ($investmentCategories as $cat) {
            // prendi l’ultimo record per questa categoria
            $last = Investment::where('user_id', auth()->id())
                ->where('family_id', $family->id ?? null)
                ->where('category_id', $cat->id)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $curr = $last ? $last->current_balance  : 0;
            $inv  = $last ? $last->invested_balance : 0;
            $profit = $curr - $inv;

            // array per la tabella
            $investmentSummary[] = [
                'id'                => $cat->id,
                'name'              => $cat->name,
                'current_balance'   => $curr,
                'invested_balance'  => $inv,
                'profit'            => $profit,
            ];

            // array per pre‐popolare la modale
            $latestInvestments[$cat->id] = [
                'current_balance'   => $curr,
                'invested_balance'  => $inv,
            ];
        }

        $viewData = [
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
            'incomeByCategory'   => $incomeByCategory ?? [],
            'expenseByCategory'  => $expenseByCategory ?? [],
            'budgetTotalByCategory'   => $budgetTotalByCategory,
            'assignedTotal'         => $assignedTotal,
            'summaryIncomeByMonth'  => $summaryIncomeByMonth,
            'summaryExpenseByMonth' => $summaryExpenseByMonth,
            'summaryGainByMonth'    => $summaryGainByMonth,
            'totalSummaryIncome'    => $totalSummaryIncome,
            'totalSummaryExpense'   => $totalSummaryExpense,
            'totalSummaryGain'      => $totalSummaryGain,
            'investmentCategories' => $investmentCategories,
            'investmentSummary'    => $investmentSummary,
            'latestInvestments'    => $latestInvestments,
        ];

        // === aggiungi alla view i 5 valori centralizzati ===
        $viewData['familiare'] = $familiare;
        $viewData['extra']     = $extra;
        $viewData['risparmi']  = $risparmi;
        $viewData['personale'] = $personale;
        $viewData['totale']    = $totale;

        return view('home', array_merge($viewData));
    }
}
