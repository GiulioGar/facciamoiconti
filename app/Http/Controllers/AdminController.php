<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Family;

class AdminController extends Controller
{
    public function __construct()
    {
        // Tutte le pagine admin richiedono login
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $user = Auth::user();

        if ($user->role === 'capofamiglia') {
            // Dati per il capofamiglia
            $ownFamily = $user->ownedFamilies()->first();
            $pendingRequests = $ownFamily
                ? $ownFamily->members()->wherePivot('status','pending')->get()
                : collect();

            return view('admin.dashboard', compact('ownFamily', 'pendingRequests'));
        }

        // Dati per il membro
        $inFamily    = $user->families()->wherePivot('status','accepted')->exists();
        $hasPending  = $user->families()->wherePivot('status','pending')->exists();
        $families    = Family::withCount(['members' => function($q){
                          $q->where('status','accepted');
                        }])->get();

        return view('admin.dashboard', compact('inFamily','hasPending','families'));
    }
}
