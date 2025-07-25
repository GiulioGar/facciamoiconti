<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;
use App\Models\IncomeAllocation;
use App\Models\BudgetCategory;
use Carbon\Carbon;
use App\Models\Family;

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
    ]);

    $income = Income::create([
        'description' => $data['description'],
        'amount'      => $data['amount'],
        'date'        => $data['date'],
        'family_id'   => $data['family_id'],
        'user_id'     => Auth::id(),
    ]);

    // Precarico uno mappatura id → slug (o qualunque stringa voglia tu usare come "type")
    $typeMap = BudgetCategory::pluck('slug', 'id')->toArray();

    foreach ($data['allocations'] as $categoryId => $value) {
        if ($value > 0) {
            $income->allocations()->create([
                'category_id' => $categoryId,
                'amount'      => $value,
                // qui associo sempre un "type" valido
                'type'        => $typeMap[$categoryId] ?? 'category',
            ]);
        }
    }

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
