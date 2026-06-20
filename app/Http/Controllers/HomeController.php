<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // === aggiunto ===
use App\Services\FamilyBudgetSummary;   // === aggiunto ===
use App\Models\Family;
use App\Models\FinancialBalance;
use App\Models\WalletMovement;
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

        // 4) Calcolo saldi per il periodo selezionato
        $periodEnd = $period->copy()->endOfMonth()->toDateString();
        $familyId  = $family->id ?? 0;

        // Bank e cash: somma movimenti wallet fino alla fine del mese selezionato
        $bankAtPeriod = (float) WalletMovement::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->where('account', 'bank')
            ->whereDate('date', '<=', $periodEnd)
            ->sum('amount');

        $cashAtPeriod = (float) WalletMovement::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->where('account', 'cash')
            ->whereDate('date', '<=', $periodEnd)
            ->sum('amount');

        // Altri campi: dall'ultimo record manuale <= fine periodo
        $manualAtPeriod = FinancialBalance::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->whereDate('accounting_month', '<=', $periodEnd)
            ->orderBy('accounting_month', 'desc')
            ->first();

        $balance = new FinancialBalance([
            'user_id'          => $user->id,
            'family_id'        => $familyId,
            'bank_balance'     => $bankAtPeriod,
            'other_accounts'   => $manualAtPeriod->other_accounts ?? 0,
            'cash'             => $cashAtPeriod,
            'insurances'       => $manualAtPeriod->insurances     ?? 0,
            'investments'      => $manualAtPeriod->investments     ?? 0,
            'debt_credit'      => $manualAtPeriod->debt_credit     ?? 0,
            'accounting_month' => $periodDate,
        ]);

        $hasBalance = WalletMovement::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->exists()
            || FinancialBalance::where('user_id', $user->id)
                ->where('family_id', $familyId)
                ->exists();

        // 5) Calcoli saldi periodo
        $total  = $balance->bank_balance
                 + $balance->other_accounts
                 + $balance->cash
                 + $balance->insurances
                 + $balance->investments
                 + $balance->debt_credit;
        $liquid = $balance->bank_balance
                 + $balance->other_accounts
                 + $balance->cash;

        // 6) Saldi complessivi più recenti (tutti i movimenti/record)
        $latestBankBalance = (float) WalletMovement::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->where('account', 'bank')
            ->sum('amount');

        $latestCashBalance = (float) WalletMovement::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->where('account', 'cash')
            ->sum('amount');

        $latestManual = FinancialBalance::where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->orderBy('accounting_month', 'desc')
            ->first();

        $latestBalance = new FinancialBalance([
            'bank_balance'   => $latestBankBalance,
            'other_accounts' => $latestManual->other_accounts ?? 0,
            'cash'           => $latestCashBalance,
            'insurances'     => $latestManual->insurances     ?? 0,
            'investments'    => $latestManual->investments     ?? 0,
            'debt_credit'    => $latestManual->debt_credit     ?? 0,
        ]);

        $latestTotal  = $latestBalance->bank_balance
                      + $latestBalance->other_accounts
                      + $latestBalance->cash
                      + $latestBalance->insurances
                      + $latestBalance->investments
                      + $latestBalance->debt_credit;

        $latestLiquid = $latestBalance->bank_balance
                      + $latestBalance->other_accounts
                      + $latestBalance->cash;

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
                ->where('i.family_id', $familyId)
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
                ->where('family_id', $familyId)
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
                ->where('i.family_id',       $familyId)
                ->sum('ia.amount');

            $totalExpenseAllYears[$cat->id] = DB::table('expenses')
                ->where('budget_category_id', $cat->id)
                ->where('user_id',            $user->id)
                ->where('family_id',          $familyId)
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
            $start     = Auth::user()->is_admin ? $cat->start_amount : 0;

            $budgetTotalByCategory[$cat->id] = $start + $sumIncAll - $sumExpAll;
        }

        // Calcolo del totale assegnato a tutti i budget
        $assignedTotal = array_sum($budgetTotalByCategory);

        // ————————————————————————
        // Resoconto Mensile Totale
        // ————————————————————————
        $summaryIncomeByMonth = DB::table('incomes')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->whereYear('date', $currentYear)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        $summaryExpenseByMonth = DB::table('expenses')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->id)
            ->where('family_id', $familyId)
            ->whereYear('date', $currentYear)
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

        $latestInvestmentsByCategory = Investment::where(‘user_id’, $user->id)
            ->where(‘family_id’, $family->id ?? null)
            ->orderByDesc(‘created_at’)
            ->orderByDesc(‘id’)
            ->get()
            ->groupBy(‘category_id’)
            ->map(function ($items) { return $items->first(); });

        foreach ($investmentCategories as $cat) {
            $last = $latestInvestmentsByCategory->get($cat->id);

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

        return view('home', $viewData);
    }


public function history(Request $request)
{
    $user = Auth::user();

    // ===============================
    // 1) Gestione ANNI (robusta)
    // ===============================
    $maxYear = Carbon::now()->year;
    $minYear = 2025;

    $year = (int) $request->get('year', $maxYear);
    $year = max(min($year, $maxYear), $minYear);

    // Confronto attivo SOLO dal 2026 in poi
    $comparisonEnabled = $year > 2025;
    $previousYear = $comparisonEnabled ? $year - 1 : null;

    // ===============================
    // 2) Famiglia (stessa logica Home)
    // ===============================
    if ($user->role === 'capofamiglia') {
        $family = Family::where('owner_id', $user->id)->first();
    } else {
        $family = $user->families()
            ->wherePivot('status', 'accepted')
            ->first();
    }

    if (! $family) {
        abort(403);
    }

    // ===============================
    // 3) Categorie
    // ===============================
    $categories = BudgetCategory::orderBy('sort_order')->get();

    $incomeByCategory  = [];
    $expenseByCategory = [];

    foreach ($categories as $cat) {

        // ENTRATE per mese (anno selezionato)
        $incomeByCategory[$cat->id] = DB::table('income_allocations as ia')
            ->join('incomes as i', 'ia.income_id', '=', 'i.id')
            ->select(
                DB::raw('MONTH(i.date) as month'),
                DB::raw('SUM(ia.amount) as total')
            )
            ->where('ia.category_id', $cat->id)
            ->where('i.user_id', $user->id)
            ->where('i.family_id', $family->id)
            ->whereYear('i.date', $year)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        // USCITE per mese (anno selezionato)
        $expenseByCategory[$cat->id] = DB::table('expenses')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->where('budget_category_id', $cat->id)
            ->where('user_id', $user->id)
            ->where('family_id', $family->id)
            ->whereYear('date', $year)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();
    }

    // ===============================
    // 4) RESOCONTO ANNO SELEZIONATO
    // ===============================
    $summaryIncomeByMonth = DB::table('incomes')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
        ->where('user_id', $user->id)
        ->where('family_id', $family->id)
        ->whereYear('date', $year)
        ->groupBy('month')
        ->pluck('total','month')
        ->toArray();

    $summaryExpenseByMonth = DB::table('expenses')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
        ->where('user_id', $user->id)
        ->where('family_id', $family->id)
        ->whereYear('date', $year)
        ->groupBy('month')
        ->pluck('total','month')
        ->toArray();

    $summaryGainByMonth = [];
    for ($m = 1; $m <= 12; $m++) {
        $summaryGainByMonth[$m] =
            intval($summaryIncomeByMonth[$m] ?? 0)
          - intval($summaryExpenseByMonth[$m] ?? 0);
    }

    // =====================================================
    // 5) RESOCONTO ANNO PRECEDENTE (SOLO SE ABILITATO)
    // =====================================================
    $prevSummaryIncomeByMonth = [];
    $prevSummaryExpenseByMonth = [];
    $prevSummaryGainByMonth   = [];

    if ($comparisonEnabled) {

        $prevSummaryIncomeByMonth = DB::table('incomes')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->id)
            ->where('family_id', $family->id)
            ->whereYear('date', $previousYear)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        $prevSummaryExpenseByMonth = DB::table('expenses')
            ->select(DB::raw('MONTH(date) as month'), DB::raw('SUM(amount) as total'))
            ->where('user_id', $user->id)
            ->where('family_id', $family->id)
            ->whereYear('date', $previousYear)
            ->groupBy('month')
            ->pluck('total','month')
            ->toArray();

        for ($m = 1; $m <= 12; $m++) {
            $prevSummaryGainByMonth[$m] =
                intval($prevSummaryIncomeByMonth[$m] ?? 0)
              - intval($prevSummaryExpenseByMonth[$m] ?? 0);
        }
    }

    // ===============================
    // 6) View
    // ===============================
    return view('home-history', [
        'year'                       => $year,
        'minYear'                    => $minYear,
        'maxYear'                    => $maxYear,
        'comparisonEnabled'          => $comparisonEnabled,
        'previousYear'               => $previousYear,

        'categories'                 => $categories,
        'incomeByCategory'           => $incomeByCategory,
        'expenseByCategory'          => $expenseByCategory,

        'summaryIncomeByMonth'       => $summaryIncomeByMonth,
        'summaryExpenseByMonth'      => $summaryExpenseByMonth,
        'summaryGainByMonth'         => $summaryGainByMonth,

        'prevSummaryIncomeByMonth'   => $prevSummaryIncomeByMonth,
        'prevSummaryExpenseByMonth'  => $prevSummaryExpenseByMonth,
        'prevSummaryGainByMonth'     => $prevSummaryGainByMonth,
    ]);
}


}


