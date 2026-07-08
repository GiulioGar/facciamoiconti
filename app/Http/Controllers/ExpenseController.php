<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\BudgetCategory;
use App\Models\Family;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\FinancialBalance;
use App\Models\WalletMovement;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Elenco spese (index)
     */
    public function index()
    {
        $user = Auth::user();

        // Cerco la famiglia: o owner, o membro accepted
        $family = Family::where('owner_id', $user->id)
            ->orWhereHas('members', function($q) use($user) {
                $q->where('user_id', $user->id)
                  ->where('status','accepted');
            })->first();

        // Se non c'è una famiglia valida, mostro un avviso/vista
        if (! $family) {
            return view('expenses.index', [
                'expenses' => collect(),
                'family'   => null,
            ])->with('warning','Devi prima creare o aderire a una famiglia');
        }

        // Carica le uscite solo dell'utente corrente
        $expenses = Expense::with(['expenseCategory','budgetCategory'])
            ->where('family_id', $family->id)
            ->where('user_id',   $user->id)               // ← filtro aggiunto
            ->orderBy('date','desc')
            ->orderBy('id',   'desc')
            ->get();

        $expCats    = ExpenseCategory::orderBy('sort_order')->get();
        $budgetCats = BudgetCategory::orderBy('sort_order')->get();

        // Genera un array di mesi (YYYY-MM) dall'attuale a 3 anni fa
        $months = collect();
        for ($i = 0; $i <= 36; $i++) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $walletByExpense = WalletMovement::where('source_type', 'expense')
                            ->whereIn('source_id', $expenses->pluck('id'))
                            ->get()
                            ->keyBy('source_id');

        return view('expenses.index', compact(
            'expenses',
            'family',
            'expCats',
            'budgetCats',
            'months',
            'walletByExpense'
        ));
    }

    /**
     * Mostra form per creare una nuova spesa (create)
     */
    public function create()
    {
        $user   = Auth::user();
        $family = Family::where('owner_id', $user->id)
            ->orWhereHas('members', function($q) use($user) {
                $q->where('user_id', $user->id)
                  ->where('status','accepted');
            })->first();

        if (! $family) {
            return redirect()->route('expenses.index')
                             ->with('warning','Devi prima creare o aderire a una famiglia');
        }

        $expCats    = ExpenseCategory::orderBy('sort_order')->get();
        $budgetCats = BudgetCategory::orderBy('sort_order')->get();

        $months = collect();
        for ($i = 0; $i <= 36; $i++) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        return view('expenses.create', compact(
            'family','expCats','budgetCats','months'
        ));
    }

    /**
     * Salva la nuova spesa (store)
     */
public function store(Request $request)
{
    $data = $request->validate([
        'amount'               => 'required|numeric|min:0.01',
        'date'                 => 'required|date', // YYYY-MM-DD dal calendario
        'expense_category_id'  => 'required|exists:expense_categories,id',
        'budget_category_id'   => 'required|exists:budget_categories,id',
        'note'                 => 'nullable|string',
        'family_id'            => 'required|exists:families,id',
        'wallet_allocation'    => 'required|in:bank,cash,none',
    ]);

    if (! Auth::user()->belongsToFamily((int) $data['family_id'])) {
        abort(403, 'Non sei membro di questa famiglia.');
    }

    $userId   = Auth::id();
    $familyId = (int) $data['family_id'];
    $amount   = (float) $data['amount'];

    // Data completa selezionata dall'utente
    $date = Carbon::parse($data['date'])->toDateString();

    DB::transaction(function () use ($data, $userId, $familyId, $amount, $date) {

        // 1) Crea la SPESA
        $expCat  = ExpenseCategory::findOrFail($data['expense_category_id']);

        $expense = Expense::create([
            'description'         => $expCat->name,
            'amount'              => $amount,
            'date'                => $date,
            'expense_category_id' => $data['expense_category_id'],
            'budget_category_id'  => $data['budget_category_id'],
            'note'                => $data['note'] ?? null,
            'family_id'           => $familyId,
            'user_id'             => $userId,
        ]);

        // 2) Registra il movimento wallet (solo se allocata)
        if ($data['wallet_allocation'] !== 'none') {
            WalletMovement::create([
                'user_id'     => $userId,
                'family_id'   => $familyId,
                'account'     => $data['wallet_allocation'], // 'bank' o 'cash'
                'amount'      => -$amount,                   // negativo = uscita
                'source_type' => 'expense',
                'source_id'   => $expense->id,
                'date'        => $date,
                'note'        => $expCat->name,
            ]);
        }
    });

    return redirect()
        ->route('expenses.index')
        ->with('success', 'Spesa registrata con successo');
}



    public function update(Request $request, Expense $expense)
    {
        if ($expense->user_id !== Auth::id()) abort(403);

        $data = $request->validate([
            'amount'               => 'required|numeric|min:0.01',
            'date'                 => 'required|date',
            'expense_category_id'  => 'required|exists:expense_categories,id',
            'budget_category_id'   => 'required|exists:budget_categories,id',
            'note'                 => 'nullable|string',
            'family_id'            => 'required|exists:families,id',
            'wallet_allocation'    => 'required|in:bank,cash,none',
        ]);

        if (! Auth::user()->belongsToFamily((int) $data['family_id'])) {
            abort(403, 'Non sei membro di questa famiglia.');
        }

        DB::transaction(function() use ($expense, $data) {
            $oldAmount = (float) $expense->amount;
            $expCat = ExpenseCategory::findOrFail($data['expense_category_id']);

            $expense->update([
                'description'         => $expCat->name,
                'amount'              => $data['amount'],
                'date'                => Carbon::parse($data['date'])->toDateString(),
                'expense_category_id' => $data['expense_category_id'],
                'budget_category_id'  => $data['budget_category_id'],
                'note'                => $data['note'] ?? null,
            ]);

            // Movimento wallet collegato (spese nuove, create dopo il sistema wallet)
            $linked = WalletMovement::where('source_type', 'expense')
                          ->where('source_id', $expense->id)
                          ->first();

            // Rettifica adjustment per spese legacy (create prima del sistema wallet)
            $correction = WalletMovement::where('source_type', 'adjustment')
                              ->where('source_id', $expense->id)
                              ->first();

            if ($data['wallet_allocation'] === 'none') {
                if ($linked)     $linked->delete();
                if ($correction) $correction->delete();
            } elseif ($linked) {
                // Spesa moderna con movimento collegato: aggiorna importo pieno
                $linked->update([
                    'account' => $data['wallet_allocation'],
                    'amount'  => -(float) $data['amount'],
                    'date'    => Carbon::parse($data['date'])->toDateString(),
                    'note'    => $expCat->name,
                ]);
            } else {
                // Spesa legacy: registra solo il delta come rettifica
                // delta positivo = spesa diminuita = saldo aumenta
                $delta = $oldAmount - (float) $data['amount'];
                if ($correction) {
                    $correction->update([
                        'account' => $data['wallet_allocation'],
                        'amount'  => $correction->amount + $delta,
                        'date'    => Carbon::parse($data['date'])->toDateString(),
                        'note'    => 'Rettifica: ' . $expCat->name,
                    ]);
                } elseif (abs($delta) > 0.001) {
                    WalletMovement::create([
                        'user_id'     => Auth::id(),
                        'family_id'   => (int) $data['family_id'],
                        'account'     => $data['wallet_allocation'],
                        'amount'      => $delta,
                        'source_type' => 'adjustment',
                        'source_id'   => $expense->id,
                        'date'        => Carbon::parse($data['date'])->toDateString(),
                        'note'        => 'Rettifica: ' . $expCat->name,
                    ]);
                }
            }
        });

        return back()->with('success', 'Spesa aggiornata con successo');
    }

    public function destroy(Expense $expense)
    {
        if ($expense->user_id !== Auth::id()) abort(403);

        DB::transaction(function() use ($expense) {
            $linked = WalletMovement::where('source_type', 'expense')
                          ->where('source_id', $expense->id)
                          ->first();

            $correction = WalletMovement::where('source_type', 'adjustment')
                              ->where('source_id', $expense->id)
                              ->first();

            if ($linked) {
                // Spesa moderna: elimina il movimento collegato, saldo si aggiusta da solo
                $linked->delete();
            } else {
                // Spesa legacy: crea rettifica positiva per compensare il saldo di apertura
                // original_bulk_expense = amount + correction (perché delta spesa = old - new)
                $correctionAmount = $correction ? (float) $correction->amount : 0.0;
                $originalBulkExpense = (float) $expense->amount + $correctionAmount;
                $account = $correction ? $correction->account : 'bank';

                if (abs($originalBulkExpense) > 0.001) {
                    WalletMovement::create([
                        'user_id'     => Auth::id(),
                        'family_id'   => $expense->family_id,
                        'account'     => $account,
                        'amount'      => $originalBulkExpense, // positivo = compensazione spesa rimossa
                        'source_type' => 'adjustment',
                        'source_id'   => null,
                        'date'        => $expense->date,
                        'note'        => 'Rettifica cancellazione: ' . $expense->description,
                    ]);
                }
            }

            if ($correction) $correction->delete();

            $expense->delete();
        });

        return back()->with('success', 'Spesa eliminata con successo');
    }
}
