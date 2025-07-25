<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    // 1. Lista tutte le famiglie
    public function index()
    {
        $families = Family::withCount(['members' => function($q) {
            $q->where('status','accepted');
        }])->get();
        return view('families.index', compact('families'));
    }

    // 2. Form per capofamiglia
    public function create()
    {
        return view('families.create');
    }

    // 3. Salva nuova famiglia
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

    // 4. Join request per membro
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

    // 5. Vedi richieste pendenti (solo owner)
    public function requests(Family $family)
    {
        $this->authorize('manage', $family);
        $pending = $family->members()->wherePivot('status','pending')->get();
        return view('families.requests', compact('family','pending'));
    }

    // 6. Risposta a richiesta
    public function respond(Family $family, User $user, Request $request)
    {
        $this->authorize('manage', $family);
        $action = $request->input('action'); // 'accepted' o 'rejected'
        $family->members()->updateExistingPivot($user->id, ['status'=>$action]);
        return back()->with('success',"Richiesta $action con successo.");
    }
}
