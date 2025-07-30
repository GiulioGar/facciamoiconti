<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\User;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    /**
     * 1. Lista tutte le famiglie
     */
    public function index()
    {
        $families = Family::withCount(['members' => function($q) {
            $q->where('status','accepted');
        }])->get();

        return view('families.index', compact('families'));
    }

    /**
     * 2. Form per creazione famiglia
     */
    public function create()
    {
        return view('families.create');
    }

    /**
     * 3. Salva nuova famiglia
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Se il Capofamiglia ha già una famiglia, blocca
        if ($user->ownedFamilies()->exists()) {
            return redirect()->route('home')
                             ->with('warning', 'Hai già creato una famiglia.');
        }

        $data = $request->validate([
            'nickname' => 'required|string|max:255|unique:families',
        ]);
        $data['owner_id'] = $user->id;
        Family::create($data);

        return redirect()->route('home')
                         ->with('success','Famiglia creata con successo.');
    }

    /**
     * 4. Invia richiesta di join (membro)
     */
    public function join(Family $family)
    {
        $user = Auth::user();

        // Se è già membro accettato di una famiglia, blocca
        if ($user->families()->wherePivot('status','accepted')->exists()) {
            return back()->with('warning','Sei già membro di una famiglia.');
        }

        // Se ha già una richiesta pendente verso questa o altra famiglia, blocca
        if ($user->families()->wherePivot('status','pending')->exists()) {
            return back()->with('warning','Hai già una richiesta in sospeso.');
        }

        // Attacca con status pending
        $family->members()->attach($user->id, ['status'=>'pending']);
        return back()->with('success','Richiesta inviata.');
    }

    /**
     * 5. Vedi richieste pendenti (solo owner)
     */
    public function requests(Family $family)
    {
        $this->authorize('manage', $family);
        $pending = $family->members()->wherePivot('status','pending')->get();
        return view('families.requests', compact('family','pending'));
    }

    /**
     * 6. Risposta a richiesta (accept/reject)
     */
    public function respond(Family $family, User $user, Request $request)
    {
        $this->authorize('manage', $family);
        $action = $request->input('action'); // 'accepted' o 'rejected'
        $family->members()->updateExistingPivot($user->id, ['status'=>$action]);
        return back()->with('success',"Richiesta $action con successo.");
    }

    /**
     * 7. Conti uniti: dettagli annuali, riepilogo e differenza
     */
    public function combinedBalances(Family $family)
    {
        $user = auth()->user();

        // Autorizzazione: solo owner o membro
        if ($family->owner_id !== $user->id
            && ! $family->members->contains($user)
        ) {
            abort(403, 'Non sei autorizzato ad accedere a questa famiglia');
        }

        // ID delle categorie da includere
        $commonIds = [2,3,4,17,24,7,8,9,10,11,12,13,16,18,19,20,21];

        // Carico le categorie
        $categories = ExpenseCategory::whereIn('id', $commonIds)->get();

        // Elenco utenti: prima owner poi membri
        $users = collect([$family->owner])->merge($family->members);

        // Dati per tabella dettagli anno corrente
        $data = [];
        foreach ($categories as $category) {
            $row = ['category' => $category->name, 'values' => []];
            foreach ($users as $member) {
                $row['values'][$member->id] = $member->expenses()
                    ->where('expense_category_id', $category->id)
                    ->whereYear('date', now()->year)
                    ->sum('amount');
            }
            $data[] = $row;
        }

        // Totali anno corrente
$ownerSum = $family->owner
    ->expenses()
    ->whereIn('expense_category_id', $commonIds)
    ->whereYear('date', now()->year)
    ->sum('amount');

$memberTotals = $family->members->mapWithKeys(function($member) use ($commonIds) {
    $sum = $member->expenses()
        ->whereIn('expense_category_id', $commonIds)
        ->whereYear('date', now()->year)
        ->sum('amount');  // <— qui specifica 'amount'
    return [$member->id => $sum];
});

        // Spese all-time (tutte le date) per differenza
        $allTimeOwnerSum = $family->owner
            ->expenses()
            ->whereIn('expense_category_id', $commonIds)
            ->sum('amount');

        $firstMember = $family->members->first();
        $allTimeMemberSum = $firstMember
            ->expenses()
            ->whereIn('expense_category_id', $commonIds)
            ->sum('amount');

        // Credito fisso
        $credit = 1269;

        // Net owner dopo credito
        $netOwner = $allTimeOwnerSum + $credit;

        // Differenza: proprietario in debito (+) o credito (–)
        $diff = $netOwner - $allTimeMemberSum;

        return view('families.combined-balances', compact(
            'family',
            'categories',
            'data',
            'users',
            'ownerSum',
            'memberTotals',
            'allTimeOwnerSum',
            'allTimeMemberSum',
            'credit',
            'diff',
            'firstMember' 
        ));
    }
}