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
use App\Models\WalletMovement;

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

    if (! Auth::user()->belongsToFamily((int) $data['family_id'])) {
        abort(403, 'Non sei membro di questa famiglia.');
    }

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

        // 3) Registra il movimento wallet se richiesto
        if ($data['wallet_allocation'] !== 'none') {
            WalletMovement::create([
                'user_id'     => Auth::id(),
                'family_id'   => (int) $data['family_id'],
                'account'     => $data['wallet_allocation'], // 'bank' o 'cash'
                'amount'      => (float) $data['amount'],    // positivo = entrata
                'source_type' => 'income',
                'source_id'   => $income->id,
                'date'        => $data['date'],
                'note'        => $data['description'],
            ]);
        }
    });

    return back()->with('success', 'Entrata aggiunta con successo');
}

public function update(Request $request, $id)
{
    $income = Income::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

    $data = $request->validate([
        'description'       => 'required|string|max:255',
        'amount'            => 'required|numeric|min:0.01',
        'date'              => 'required|date',
        'allocations.*'     => 'nullable|numeric|min:0',
        'family_id'         => 'required|exists:families,id',
        'wallet_allocation' => 'required|in:bank,cash,none',
    ]);

    if (! Auth::user()->belongsToFamily((int) $data['family_id'])) {
        abort(403, 'Non sei membro di questa famiglia.');
    }

    DB::transaction(function() use ($income, $data) {
        $oldAmount = (float) $income->amount;

        $income->update([
            'description' => $data['description'],
            'amount'      => $data['amount'],
            'date'        => $data['date'],
        ]);

        $income->allocations()->delete();
        $typeMap = BudgetCategory::pluck('slug', 'id')->toArray();
        if (!empty($data['allocations'])) {
            foreach ($data['allocations'] as $categoryId => $value) {
                if ((float)$value > 0) {
                    $income->allocations()->create([
                        'category_id' => $categoryId,
                        'amount'      => $value,
                        'type'        => $typeMap[$categoryId] ?? 'category',
                    ]);
                }
            }
        }

        // Movimento wallet collegato (entrate nuove, create dopo il sistema wallet)
        $linked = WalletMovement::where('source_type', 'income')
                      ->where('source_id', $income->id)
                      ->first();

        // Rettifica adjustment per entrate legacy (create prima del sistema wallet)
        $correction = WalletMovement::where('source_type', 'adjustment')
                          ->where('source_id', $income->id)
                          ->first();

        if ($data['wallet_allocation'] === 'none') {
            if ($linked)      $linked->delete();
            if ($correction)  $correction->delete();
        } elseif ($linked) {
            // Entrata nuova con movimento collegato: aggiorna importo pieno
            $linked->update([
                'account' => $data['wallet_allocation'],
                'amount'  => (float) $data['amount'],
                'date'    => $data['date'],
                'note'    => $data['description'],
            ]);
        } else {
            // Entrata legacy: registra solo il delta come rettifica
            $delta = (float) $data['amount'] - $oldAmount;
            if ($correction) {
                $correction->update([
                    'account' => $data['wallet_allocation'],
                    'amount'  => $correction->amount + $delta,
                    'date'    => $data['date'],
                    'note'    => 'Rettifica: ' . $data['description'],
                ]);
            } elseif (abs($delta) > 0.001) {
                WalletMovement::create([
                    'user_id'     => Auth::id(),
                    'family_id'   => (int) $data['family_id'],
                    'account'     => $data['wallet_allocation'],
                    'amount'      => $delta,
                    'source_type' => 'adjustment',
                    'source_id'   => $income->id,
                    'date'        => $data['date'],
                    'note'        => 'Rettifica: ' . $data['description'],
                ]);
            }
        }
    });

    return back()->with('success', 'Entrata aggiornata con successo');
}

public function destroy($id)
{
    $income = Income::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

    DB::transaction(function() use ($income) {
        $linked = WalletMovement::where('source_type', 'income')
                      ->where('source_id', $income->id)
                      ->first();

        $correction = WalletMovement::where('source_type', 'adjustment')
                          ->where('source_id', $income->id)
                          ->first();

        if ($linked) {
            // Entrata moderna: elimina il movimento collegato, saldo si aggiusta da solo
            $linked->delete();
        } else {
            // Entrata legacy: l'importo era nel saldo di apertura, occorre una rettifica negativa
            // original_bulk_amount = income->amount - (eventuale correzione già applicata)
            $correctionAmount = $correction ? (float) $correction->amount : 0.0;
            $originalBulkAmount = (float) $income->amount - $correctionAmount;
            $account = $correction ? $correction->account : 'bank';

            if (abs($originalBulkAmount) > 0.001) {
                WalletMovement::create([
                    'user_id'     => Auth::id(),
                    'family_id'   => $income->family_id,
                    'account'     => $account,
                    'amount'      => -$originalBulkAmount,
                    'source_type' => 'adjustment',
                    'source_id'   => null,
                    'date'        => $income->date,
                    'note'        => 'Rettifica cancellazione: ' . $income->description,
                ]);
            }
        }

        if ($correction) $correction->delete();

        $income->allocations()->delete();
        $income->delete();
    });

    return back()->with('success', 'Entrata eliminata con successo');
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
                     ->get();

    $categories = BudgetCategory::orderBy('sort_order')->get();

    $walletByIncome = WalletMovement::where('source_type', 'income')
                        ->whereIn('source_id', $incomes->pluck('id'))
                        ->get()
                        ->keyBy('source_id');

    return view('incomes.index', compact(
        'incomes','family','categories','walletByIncome'
    ));
}
}
