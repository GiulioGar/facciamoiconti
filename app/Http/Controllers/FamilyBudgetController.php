<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Family;
use App\Services\FamilyBudgetSummary;

class FamilyBudgetController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Trova la famiglia attiva
        $family = null;
        if (method_exists($user, 'families')) {
            $family = $user->families()->wherePivot('status', 'accepted')->first();
        }
        if (!$family) {
            $family = Family::where('owner_id', $user->id)->first();
        }

        if (!$family) {
            return view('families.summary', [
                'family'     => null,
                'totale'     => 0.0,
                'familiare'  => 0.0,
                'extra'      => 0.0,
                'risparmi'   => 0.0,
                'personale'  => 0.0,
            ]);
        }

        // 1) Prova a leggere il JSON di cache creato in HomeController
        $cachePath = "families/{$family->id}/summary.json";
        $familiare = $extra = $risparmi = $personale = $totale = 0.0;

        if (Storage::disk('local')->exists($cachePath)) {
            $payload = json_decode(Storage::disk('local')->get($cachePath), true);
            if (is_array($payload) && isset($payload['data'])) {
                $data      = $payload['data'];
                $familiare = (float)($data['familiare'] ?? 0);
                $extra     = (float)($data['extra'] ?? 0);
                $risparmi  = (float)($data['risparmi'] ?? 0);
                $personale = (float)($data['personale'] ?? 0);
                $totale    = (float)($data['totale'] ?? 0);
            }
        }

        // 2) Fallback: se non esiste cache o è vuota, calcola via Service
        if ($totale === 0.0 && $familiare === 0.0 && $extra === 0.0 && $risparmi === 0.0 && $personale === 0.0) {
            $summary   = FamilyBudgetSummary::build($user->id, $family->id);
            $familiare = $summary['familiare'];
            $extra     = $summary['extra'];
            $risparmi  = $summary['risparmi'];
            $personale = $summary['personale'];
            $totale    = $summary['totale'];
        }

        return view('families.summary', compact(
            'family',
            'totale',
            'familiare',
            'extra',
            'risparmi',
            'personale'
        ));
    }
}
