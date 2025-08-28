<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FantaQuotazione;
use App\Models\FantaListone;
use App\Models\FantaRosa;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class FantacalcioController extends Controller
{
    public function index()
    {
        $listone = FantaListone::orderBy('ruolo')
            ->orderBy('squadra')
            ->orderBy('nome')
            ->paginate(25);

        return view('fantacalcio.index', compact('listone'));
    }

    public function quote()
    {
        return view('fantacalcio.quote');
    }

    // --- IMPORT CSV per fanta_quotazione ---
    public function quoteImport(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->with('error', 'Impossibile aprire il file.');
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        // Gestione BOM
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (strncmp($firstLine, $bom, 3) === 0) {
            fseek($handle, 3);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Header CSV mancante o non valido.');
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $required = ['id','r','rm','nome','squadra','fvm'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                return back()->with('error', "Colonna richiesta mancante: {$col}");
            }
        }

        $idx = array_flip($header);
        $rows = [];
        $convert = fn($v) => trim(mb_convert_encoding($v, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252'));

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) < count($header)) continue;

            $rows[] = [
                'external_id'  => (int) $convert($data[$idx['id']]),
                'ruolo'        => $convert($data[$idx['r']]),
                'ruolo_esteso' => $convert($data[$idx['rm']]),
                'nome'         => $convert($data[$idx['nome']]),
                'squadra'      => $convert($data[$idx['squadra']]),
                'fvm'          => (int) $convert($data[$idx['fvm']]),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->with('error', 'Nessun dato valido trovato nel CSV.');
        }

        DB::beginTransaction();
        try {
            DB::table('fanta_quotazione')->truncate();
            foreach (array_chunk($rows, 1000) as $chunk) {
                FantaQuotazione::insert($chunk);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore durante l\'import: ' . $e->getMessage());
        }

        return back()->with('success', 'Import completato. Righe inserite: ' . count($rows));
    }

    // --- SYNC listone da fanta_quotazione ---
    public function listoneSync(Request $request)
    {
        // Pre-calcolo per statistiche finali
        $existing = FantaListone::pluck('fvm', 'external_id'); // [ext_id => fvm]
        $quot     = FantaQuotazione::pluck('fvm', 'external_id');

        $toInsertIds = array_diff_key($quot->toArray(), $existing->toArray());
        $toUpdateIds = array_filter(
            array_intersect_key($quot->toArray(), $existing->toArray()),
            function ($fvm, $extId) use ($existing) {
                return (int)$existing[$extId] !== (int)$fvm;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $rows = FantaQuotazione::select('external_id','ruolo','ruolo_esteso','nome','squadra','fvm')
            ->get()
            ->map(fn($r) => [
                'external_id'  => $r->external_id,
                'ruolo'        => $r->ruolo,
                'ruolo_esteso' => $r->ruolo_esteso,
                'nome'         => $r->nome,
                'squadra'      => $r->squadra,
                'fvm'          => $r->fvm,
                'created_at'   => now(),
                'updated_at'   => now(),
            ])
            ->toArray();

        DB::beginTransaction();
        try {
            // Se non esiste, inserisce tutta la riga; se esiste, aggiorna SOLO fvm + updated_at
            DB::table('fanta_listone')->upsert(
                $rows,
                ['external_id'],
                ['fvm', 'updated_at']
            );
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore durante aggiornamento listone: ' . $e->getMessage());
        }

        return back()->with('success', "Lista aggiornata: inseriti ".count($toInsertIds).", aggiornati ".count($toUpdateIds).".");
    }

public function listoneData(Request $request)
{
    // Parametri DataTables
    $draw   = (int) $request->get('draw', 1);
    $start  = (int) $request->get('start', 0);
    $length = (int) $request->get('length', 10);

    // Filtri custom
    $name        = trim((string) $request->get('name', ''));
    $roleClassic = strtoupper(trim((string) $request->get('role_classic', ''))); // P/D/C/A
    $roleMantra  = ucfirst(strtolower(trim((string) $request->get('role_mantra', '')))); // Por/Dc/Ds/...

    $classicToMantra = [
        'P' => ['Por'],
        'D' => ['Dc','Ds','Dd','E','B'],
        'C' => ['M','C','W','T'],
        'A' => ['A','Pc'],
    ];

    $query = \App\Models\FantaListone::query();

    // Filtro per nome (solo colonna Nome)
    if ($name !== '') {
        $query->where('nome', 'like', "%{$name}%");
    }

    // Helper REGEXP-safe
    $tokenRegex = function(string $token): array {
        $safe = preg_quote($token, '/');
        return ["(^|;\\s*){$safe}(\\s*;|$)"];
    };

    // PRIORITÀ: se è selezionato Mantra, ignora Classic
    if ($roleMantra !== '') {
        $query->whereRaw("ruolo_esteso REGEXP ?", $tokenRegex($roleMantra));
    } elseif ($roleClassic !== '' && isset($classicToMantra[$roleClassic])) {
        $query->where(function ($q) use ($classicToMantra, $roleClassic, $tokenRegex) {
            foreach ($classicToMantra[$roleClassic] as $mantraRole) {
                $q->orWhereRaw("ruolo_esteso REGEXP ?", $tokenRegex($mantraRole));
            }
        });
    }

    // --- Espressioni SQL per mv24 effettivo e punteggio -----------------------
    // media mv24 per ruolo (subquery correlata)
    $avgSub = "(SELECT AVG(m2.mv24) FROM fanta_listone m2 WHERE m2.ruolo = fanta_listone.ruolo AND m2.mv24 IS NOT NULL)";
    // mv24_eff = mv24 se presente, altrimenti media per ruolo, altrimenti 1.0
    $mvEffExpr = "COALESCE(fanta_listone.mv24, {$avgSub}, 1.0)";
    // score = (fvm * mv24_eff) + (like - dislike)
    $scoreExpr = "(fanta_listone.fvm * {$mvEffExpr}) + (fanta_listone.`like` - fanta_listone.`dislike`)";

    // Conteggi
    $recordsTotal    = \App\Models\FantaListone::count();
    $recordsFiltered = (clone $query)->count();

    // Ordinamento
 // Ordinamento (multi-colonna da DataTables)
$order = $request->input('order', []);

// Mappa index colonne DataTables -> colonne DB / espressioni
$columns = [
    0  => 'stato',
    1  => 'external_id',
    2  => 'ruolo',
    3  => 'ruolo_esteso',
    4  => 'nome',
    5  => 'squadra',
    6  => 'fvm',
    7  => 'titolare',
    8  => DB::raw($mvEffExpr),   // mv24 effettivo
    9  => 'like',
    10 => 'dislike',
    11 => DB::raw($scoreExpr),   // punteggio
];

if (!empty($order)) {
    foreach ($order as $ord) {
        $idx = (int)($ord['column'] ?? 0);
        $dir = (($ord['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
        $col = $columns[$idx] ?? 'ruolo';

        if ($col instanceof \Illuminate\Database\Query\Expression) {
            $query->orderByRaw($col->getValue().' '.$dir);
        } else {
            $query->orderBy($col, $dir);
        }
    }
} else {
    // Fallback se DataTables non manda 'order'
    $query->orderByRaw($scoreExpr.' desc')
          ->orderBy('like', 'desc')
          ->orderBy('titolare', 'asc')
          ->orderBy('nome', 'asc');
}

    // Se vuoi un ordinamento secondario stabile:
    //$query->orderBy('ruolo')->orderBy('nome');

    // Paginazione + selezione colonne (includo raw per alias utili nel mapping)
    $rows = $query
        ->skip($start)
        ->take($length)
        ->select([
            'id',
            'external_id',
            'ruolo',
            'ruolo_esteso',
            'nome',
            'squadra',
            'fvm',
            'titolare',
            'stato',
            'like',
            'dislike',
            'mv24',
            DB::raw("{$mvEffExpr} as mv24_eff"),
            DB::raw("{$scoreExpr} as score_calc"),
        ])
        ->get();

    // Output dati nell'ordine colonne del thead aggiornato
    $data = $rows->map(function ($r) {
        $mv24_display = $r->mv24 === null
        ? 'N.D.'
        : number_format((float)$r->mv24, 2, '.', '');

return [
        (int) $r->stato,                    // 0 - Asta
        $r->external_id,                    // 1 - ID
        $r->ruolo,                          // 2 - Ruolo
        $r->ruolo_esteso,                   // 3 - Mantra
        $r->nome,                           // 4 - Nome
        $r->squadra,                        // 5 - Squadra
        (string) (int) round($r->fvm),      // 6 - FVM intero
        $r->titolare === null ? null : (int)$r->titolare, // 7 - Titolare
        $mv24_display,                      // 8 - 2024 **N.D. se null**
        (int) $r->like,                     // 9 - Like
        (int) $r->dislike,                  // 10 - Dislike
        number_format((float)$r->score_calc, 2, '.', ''), // 11 - Punteggio (usa mv24_eff)
        (int) $r->id,                       // 12 - ID DB per azioni
    ];
    });

    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}


/**
 * Incrementa like
 */
public function incrementLike($id)
{
    $p = FantaListone::findOrFail($id);
    // limiti di sicurezza opzionali: max 1000
    if ($p->like >= 1000) {
        return response()->json(['ok' => false, 'message' => 'Limite massimo raggiunto'], 422);
    }
    $p->increment('like');
    return response()->json(['ok' => true, 'like' => (int) $p->like]);
}

/**
 * Incrementa dislike
 */
public function incrementDislike($id)
{
    $p = FantaListone::findOrFail($id);
    if ($p->dislike >= 1000) {
        return response()->json(['ok' => false, 'message' => 'Limite massimo raggiunto'], 422);
    }
    $p->increment('dislike');
    return response()->json(['ok' => true, 'dislike' => (int) $p->dislike]);
}

/**
 * Toggle stato 0 <-> 1 (icona martello/asta)
 */
public function toggleStato($id)
{
    $p = FantaListone::findOrFail($id);
    $p->stato = (int)($p->stato == 1 ? 0 : 1);
    $p->save();

    return response()->json(['ok' => true, 'stato' => (int)$p->stato]);
}

public function decrementLike($id)
{
    $p = \App\Models\FantaListone::findOrFail($id);
    if ($p->like <= 0) {
        return response()->json(['ok' => false, 'message' => 'Il valore non può scendere sotto zero'], 422);
    }
    $p->decrement('like');
    return response()->json(['ok' => true, 'like' => (int)$p->like]);
}

public function decrementDislike($id)
{
    $p = \App\Models\FantaListone::findOrFail($id);
    if ($p->dislike <= 0) {
        return response()->json(['ok' => false, 'message' => 'Il valore non può scendere sotto zero'], 422);
    }
    $p->decrement('dislike');
    return response()->json(['ok' => true, 'dislike' => (int)$p->dislike]);
}


public function rosa()
{
    $teamName   = 'Azzurlions';
    $teamBudget = 2500;

    // Pesi base per ripartire IL RIMANENTE quando cambia l’insieme dei reparti aperti
    $roleBasePerc = ['P'=>0.044, 'D'=>0.14, 'C'=>0.28, 'A'=>0.496];

    // Pesi slot (come linee guida)
    $slotBase = [
        'P' => [1],
        'D' => [100,70,60,50,30,25,3,1],
        'C' => [250,150,120,80,50,40,5,2],
        'A' => [500,350,200,150,5,1],
    ];

    // STATO ATTUALE
    $spentTotal     = \App\Models\FantaRosa::sum('costo');
    $remainingTotal = max(0, $teamBudget - $spentTotal);

    $spentNowByRole = \App\Models\FantaRosa::selectRaw('classic_role, COALESCE(SUM(costo),0) as spent')
        ->groupBy('classic_role')->pluck('spent','classic_role')->all();

    $assignedRows = \App\Models\FantaRosa::whereNotNull('classic_role')
        ->whereNotNull('slot_index')
        ->get(['classic_role','slot_index','external_id','nome','squadra','costo','ruolo_esteso']);

    $assignedMap = [];
    foreach ($assignedRows as $r) {
        $assignedMap[$r->classic_role][$r->slot_index] = [
            'nome'  => $r->nome,
            'team'  => $r->squadra,
            'roles' => $r->ruolo_esteso,
            'costo' => $r->costo,
        ];
    }

    // Reparti attualmente APERTI (hanno almeno 1 slot libero)
    $openRolesNow = [];
    foreach (['P','D','C','A'] as $role) {
        $free = 0;
        foreach ($slotBase[$role] as $i => $_w) {
            if (!isset($assignedMap[$role][$i])) $free++;
        }
        if ($free > 0) $openRolesNow[] = $role;
    }

    // Carico/creo stato (singleton: id=1)
    $state = \App\Models\FantaBudgetState::query()->first(); // abbiamo inserito 1 riga in migration
    if (!$state) {
        $state = \App\Models\FantaBudgetState::create([
            'anchor_remaining' => 0,
            'caps'             => [],
            'spent_at_anchor'  => [],
            'open_roles'       => [],
        ]);
    }

    // Se l’insieme dei reparti aperti è cambiato → (ri)ancora e ricalcola caps sul RIMANENTE ATTUALE
    $openChanged = (array_values($state->open_roles) !== array_values($openRolesNow));
    if ($openChanged) {
        $sumBase = 0.0;
        foreach ($openRolesNow as $r) $sumBase += ($roleBasePerc[$r] ?? 0.0);

        $newCaps = [];
        if ($remainingTotal > 0 && $sumBase > 0) {
            foreach ($openRolesNow as $r) {
                $newCaps[$r] = (int) round($remainingTotal * ($roleBasePerc[$r] / $sumBase));
            }
        }
        // reparti chiusi hanno cap = 0
        foreach (['P','D','C','A'] as $r) {
            if (!in_array($r, $openRolesNow, true)) $newCaps[$r] = 0;
        }

        $state->update([
            'anchor_remaining' => $remainingTotal,
            'caps'             => $newCaps,
            'spent_at_anchor'  => [
                'P' => (int)($spentNowByRole['P'] ?? 0),
                'D' => (int)($spentNowByRole['D'] ?? 0),
                'C' => (int)($spentNowByRole['C'] ?? 0),
                'A' => (int)($spentNowByRole['A'] ?? 0),
            ],
            'open_roles'       => $openRolesNow,
        ]);
    }

    // Usa i caps ancorati
    $caps           = $state->caps ?: [];
    $spentAtAnchor  = $state->spent_at_anchor ?: ['P'=>0,'D'=>0,'C'=>0,'A'=>0];

    // Calcolo “da spendere” per reparto = cap - (speso_now - speso_all_anchor)
    // (mai < 0)
    $adviceByRole = [];
    foreach (['P','D','C','A'] as $role) {
        $cap          = (int)($caps[$role] ?? 0);
        $spentNow     = (int)($spentNowByRole[$role] ?? 0);
        $spentAnchor  = (int)($spentAtAnchor[$role] ?? 0);
        $toSpendRole  = max(0, $cap - max(0, $spentNow - $spentAnchor));

        // distribuisco toSpendRole SOLO tra slot liberi del ruolo
        $weights     = $slotBase[$role];
        $freeWeights = [];
        foreach ($weights as $i => $w) {
            if (!isset($assignedMap[$role][$i])) $freeWeights[] = $w;
        }
        $wSum = array_sum($freeWeights) ?: 1;

        $slotSuggested = [];
        foreach ($weights as $i => $w) {
            if (isset($assignedMap[$role][$i])) {
                $slotSuggested[$i] = 0;
            } else {
                $slotSuggested[$i] = (int) max(0, round($toSpendRole * ($w / $wSum)));
            }
        }

        $adviceByRole[$role] = [
            'suggested'      => $cap,          // target complessivo del reparto a partire dall’ancora
            'spent'          => $spentNow,     // storico attuale (per badge)
            'remaining'      => $toSpendRole,  // ancora spendibile nel reparto
            'slot_suggested' => $slotSuggested,
        ];
    }

    // Labels ruoli
    $roles = [
        'P' => ['label'=>'Portieri'],
        'D' => ['label'=>'Difensori'],
        'C' => ['label'=>'Centrocampisti'],
        'A' => ['label'=>'Attaccanti'],
    ];

    // Linee guida slot (come prima)
    $guidelines = [
        'P' => [['title'=>'1 slot','hint'=>null]],
        'D' => [
            ['title'=>'Slot 1 (semi-top Dc/E/Ds)','hint'=>'Un difensore che può portare bonus e sia titolare in una big'],
            ['title'=>'Slot 2 (Dc affidabile)','hint'=>'Centrale titolare in top 7, voto sicuro'],
            ['title'=>'Slot 3 (Dc o Ds titolare)','hint'=>'Centrale o terzino di squadra medio-alta'],
            ['title'=>'Slot 4 (E titolare medio)','hint'=>'Esterno a tutta fascia che gioca sempre'],
            ['title'=>'Slot 5 (Dc titolare low cost)','hint'=>'Centrale da 6 fisso di squadra piccola'],
            ['title'=>'Slot 6 (Dd/Ds titolare basso)','hint'=>'Terzino titolare che porta almeno il voto'],
            ['title'=>'Slot 7 (titolare 100% low cost)','hint'=>'Dc/E di provinciale che gioca sempre'],
            ['title'=>'Slot 8 (riempi slot minimo)','hint'=>'Riempi slot sempre presente nelle liste'],
        ],
        'C' => [
            ['title'=>'Slot 1 (T top)','hint'=>'Trequartista di riferimento'],
            ['title'=>'Slot 2 (C offensivo semi-top)','hint'=>'Mezzala che porta bonus'],
            ['title'=>'Slot 3 (E/W listato C)','hint'=>'Esterno/ala alto ma listato centrocampista'],
            ['title'=>'Slot 4 (C titolare buono)','hint'=>'Sempre titolare in top 7'],
            ['title'=>'Slot 5 (M titolare solido)','hint'=>'Mediano utile per coprire il modulo'],
            ['title'=>'Slot 6 (C/E titolare piccolo club)','hint'=>'Titolare in medio-bassa'],
            ['title'=>'Slot 7 (titolare low cost)','hint'=>'Mediano/mezzala di provinciale'],
            ['title'=>'Slot 8 (riempi slot 1-2 crediti)','hint'=>'Ultimi soldi, garantisca il voto'],
        ],
        'A' => [
            ['title'=>'Slot 1 (bomber top Pc)','hint'=>'Uno tra Osimhen, Lautaro, Vlahović'],
            ['title'=>'Slot 2 (semi-top A/Pc)','hint'=>'Seconda punta o prima di alto livello'],
            ['title'=>'Slot 3 (titolarissimo medio Pc)','hint'=>'Titolare di squadra media 8–12 gol'],
            ['title'=>'Slot 4 (seconda punta discreta)','hint'=>'Partner titolare in medio-bassa'],
            ['title'=>'Slot 5 (titolare/scommessa low cost)','hint'=>'Gioca spesso, costo minimo'],
            ['title'=>'Slot 6 (riempi slot a 1)','hint'=>'Vice del top o giovane'],
        ],
    ];

    $team = [
        'name'      => $teamName,
        'budget'    => $teamBudget,
        'spent'     => $spentTotal,
        'remaining' => $remainingTotal,
    ];

    return view('fantacalcio.rosa', compact(
        'team','roles','guidelines','adviceByRole','assignedMap'
    ));
}






public function rosaPlayers(Request $request)
{
    $roleClassic = strtoupper($request->query('role', '')); // 'P' | 'D' | 'C' | 'A'
    $q           = trim((string)$request->query('q', ''));

    // Classic -> Mantra (rispettando maiuscole iniziali e ruoli multipli in ruolo_esteso)
    $classicToMantra = [
        'P' => ['Por'],
        'D' => ['Dc','Ds','Dd','E','B'],
        'C' => ['M','C','W','T'],
        'A' => ['A','Pc'],
    ];

    $players = FantaListone::query()->where('stato', 0);

    // filtro per ruolo: match esatto del token in ruolo_esteso (che può avere ";" multipli)
    if ($roleClassic !== '' && isset($classicToMantra[$roleClassic])) {
        $players->where(function($qq) use ($classicToMantra, $roleClassic) {
            foreach ($classicToMantra[$roleClassic] as $mantraRole) {
                $safe = preg_quote($mantraRole, '/');
                $pattern = "(^|;\\s*){$safe}(\\s*;|$)";
                $qq->orWhereRaw("ruolo_esteso REGEXP ?", [$pattern]);
            }
        });
    }

    if ($q !== '') {
        $players->where('nome', 'like', "%{$q}%");
    }

    $players = $players
        ->orderBy('ruolo_esteso')
        ->orderBy('squadra')
        ->orderBy('nome')
        ->limit(200) // safety
        ->get(['external_id','ruolo','ruolo_esteso','nome','squadra','fvm']);

    // formato semplice per <select>
    $data = $players->map(function($p){
        return [
            'value' => $p->external_id,
            'text'  => "{$p->nome} ({$p->squadra}) — {$p->ruolo_esteso} — FVM {$p->fvm}",
        ];
    });

    return response()->json($data);
}


public function rosaAdd(Request $request)
{
    $v = Validator::make($request->all(), [
        'external_id' => ['required','integer','exists:fanta_listone,external_id','unique:fanta_rosa,external_id'],
        'costo'       => ['required','integer','min:0'],
        'role'        => ['required','in:P,D,C,A'],
        'slot_index'  => ['required','integer','min:0',
            Rule::unique('fanta_rosa','slot_index')->where(fn($q)=>$q->where('classic_role',$request->role))
        ],
    ], [
        'external_id.unique' => 'Questo giocatore è già in rosa.',
        'slot_index.unique'  => 'Questo slot è già occupato per il ruolo selezionato.',
    ]);
    if ($v->fails()) {
        return back()->withErrors($v)->with('error', 'Dati non validi.');
    }

    $teamBudget = 2500;
    $spentTotal = FantaRosa::sum('costo');
    $remaining  = $teamBudget - $spentTotal;

    if ((int)$request->costo > $remaining) {
        return back()->with('error', 'Acquisto non consentito: crediti insufficienti.');
    }

    $player = FantaListone::where('external_id', $request->external_id)
                ->where('stato', 0)
                ->firstOrFail();

    // coerenza ruolo Classic ↔ ruolo_esteso
    $classicToMantra = [
        'P' => ['Por'],
        'D' => ['Dc','Ds','Dd','E','B'],
        'C' => ['M','C','W','T'],
        'A' => ['A','Pc'],
    ];
    $ok = false;
    foreach ($classicToMantra[$request->role] as $m) {
        $safe = preg_quote($m, '/');
        if (preg_match("/(^|;\\s*){$safe}(\\s*;|$)/", $player->ruolo_esteso)) { $ok = true; break; }
    }
    if (!$ok) {
        return back()->with('error', 'Il giocatore selezionato non è compatibile con il ruolo dello slot.');
    }

    // 1) Inserisci in rosa con classic_role + slot_index
    FantaRosa::create([
        'external_id'  => $player->external_id,
        'ruolo_esteso' => $player->ruolo_esteso,
        'nome'         => $player->nome,
        'squadra'      => $player->squadra,
        'costo'        => (int)$request->costo,
        'classic_role' => $request->role,
        'slot_index'   => (int)$request->slot_index,  // ✅
    ]);

    // 2) Marca assegnato nel listone
    $player->update(['stato' => 1]);

    return back()->with('success', 'Giocatore aggiunto alla rosa. Budget e slot aggiornati.');
}





}
