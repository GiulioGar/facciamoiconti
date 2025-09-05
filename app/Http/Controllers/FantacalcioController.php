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

    // --- Espressioni SQL sicure ----------------------------------------------
    // media mv24 per ruolo (subquery correlata)
    $avgSub = "(SELECT AVG(m2.mv24) FROM fanta_listone m2 WHERE m2.ruolo = fanta_listone.ruolo AND m2.mv24 IS NOT NULL)";
    // mv24_eff = mv24 se presente, altrimenti media per ruolo, altrimenti 1.0
    $mvEffExpr = "COALESCE(fanta_listone.mv24, {$avgSub}, 1.0)";

    // 👇 CAST a signed per evitare 1690 (unsigned out-of-range nelle sottrazioni)
    $likesSigned    = "CAST(COALESCE(fanta_listone.`like`, 0) AS SIGNED)";
    $dislikesSigned = "CAST(COALESCE(fanta_listone.`dislike`, 0) AS SIGNED)";

    // score = (fvm * mv24_eff) + (like - dislike) con cast signed
    $scoreExpr = "(fanta_listone.fvm * {$mvEffExpr}) + ({$likesSigned} - {$dislikesSigned})";

    // Conteggi
    $recordsTotal    = \App\Models\FantaListone::count();
    $recordsFiltered = (clone $query)->count();

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
        8  => DB::raw($mvEffExpr),     // mv24 effettivo
        9  => DB::raw($likesSigned),   // like casted
        10 => DB::raw($dislikesSigned),// dislike casted
        11 => DB::raw($scoreExpr),     // punteggio
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
        $query->orderByRaw($scoreExpr.' DESC')
              ->orderByRaw($likesSigned.' DESC') // non usare 'like' nudo
              ->orderBy('titolare', 'asc')
              ->orderBy('nome', 'asc');
    }

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
            DB::raw('`like`   as likes'),
            DB::raw('`dislike` as dislikes'),
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
            (int) $r->stato,                                   // 0 - Asta
            $r->external_id,                                   // 1 - ID
            $r->ruolo,                                         // 2 - Ruolo
            $r->ruolo_esteso,                                  // 3 - Mantra
            $r->nome,                                          // 4 - Nome
            $r->squadra,                                       // 5 - Squadra
            (string) (int) round($r->fvm),                     // 6 - FVM intero
            $r->titolare === null ? null : (int)$r->titolare,  // 7 - Titolare
            $mv24_display,                                     // 8 - 2024
            (int) $r->likes,                                   // 9 - Like
            (int) $r->dislikes,                                // 10 - Dislike
            number_format((float)$r->score_calc, 2, '.', ''),  // 11 - Punteggio
            (int) $r->id,                                      // 12 - ID DB per azioni
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

    // --- DEFINIZIONE SLOT (26) con percentuali base --------------------------
    // NB: percentuali espresse in decimali (es. 2.8% => 0.028)
    // Aggiunte anche le due voci Portiere 1 (4.7%) e Portiere 2 (0.1%).
    // Se la somma non è esattamente 1.0, il motore rinormalizza sugli slot aperti.
    $slots = [
        // Portieri
        ['index'=>0,  'role_token'=>'Por', 'title'=>'Portiere 1', 'level'=>'Top',  'hint'=>null, 'base_perc'=>0.047],
        ['index'=>1,  'role_token'=>'Por', 'title'=>'Portiere 2', 'level'=>'Low',  'hint'=>null, 'base_perc'=>0.001],

        // Difensori centrali (DC)
        ['index'=>2,  'role_token'=>'Dc',  'title'=>'Slot 1: DC – Top',   'level'=>'Top',  'hint'=>'Centrale di prima fascia, titolarità e voti alti', 'base_perc'=>0.028],
        ['index'=>3,  'role_token'=>'Dc',  'title'=>'Slot 2: DC – Medio', 'level'=>'Medio','hint'=>'Secondo centrale affidabile di fascia media',     'base_perc'=>0.018],
        ['index'=>4,  'role_token'=>'Dc',  'title'=>'Slot 3: DC – Low',   'level'=>'Low',  'hint'=>'Centrale low-cost ma titolare',                   'base_perc'=>0.001],
        ['index'=>5,  'role_token'=>'Dc',  'title'=>'Slot 4: DC – Low',   'level'=>'Low',  'hint'=>'Quarto centrale low-cost',                        'base_perc'=>0.001],
        ['index'=>6,  'role_token'=>'Dc',  'title'=>'Slot 5: DC – Low',   'level'=>'Low',  'hint'=>'Quinto centrale low-cost di riserva',             'base_perc'=>0.001],

        // Terzini sinistri (DS)
        ['index'=>7,  'role_token'=>'Ds',  'title'=>'Slot 6: DS – Medio', 'level'=>'Medio','hint'=>'Terzino sinistro titolare fascia media',           'base_perc'=>0.032],
        ['index'=>8,  'role_token'=>'Ds',  'title'=>'Slot 7: DS – Low',   'level'=>'Low',  'hint'=>'Vice DS economico',                               'base_perc'=>0.001],

        // Terzini destri (DD)
        ['index'=>9,  'role_token'=>'Dd',  'title'=>'Slot 8: DD – Medio', 'level'=>'Medio','hint'=>'Terzino destro titolare fascia media',            'base_perc'=>0.036],
        ['index'=>10, 'role_token'=>'Dd',  'title'=>'Slot 9: DD – Low',   'level'=>'Low',  'hint'=>'Vice DD economico',                               'base_perc'=>0.001],

        // Esterni (E)
        ['index'=>11, 'role_token'=>'E',   'title'=>'Slot 10: E – Medio', 'level'=>'Medio','hint'=>'Esterno titolare di buon livello',                 'base_perc'=>0.030],
        ['index'=>12, 'role_token'=>'E',   'title'=>'Slot 11: E – Low',   'level'=>'Low',  'hint'=>'Secondo esterno economico',                        'base_perc'=>0.001],
        ['index'=>13, 'role_token'=>'E',   'title'=>'Slot 12: E – Low (jolly)','level'=>'Low','hint'=>'Terzo esterno/jolly low-cost','base_perc'=>0.001],

        // Mediani (M)
        ['index'=>14, 'role_token'=>'M',   'title'=>'Slot 13: M – Medio', 'level'=>'Medio','hint'=>'Mediano titolare per 3-4-2-1',                     'base_perc'=>0.026],
        ['index'=>15, 'role_token'=>'M',   'title'=>'Slot 14: M – Low',   'level'=>'Low',  'hint'=>'Mediano di scorta economico',                      'base_perc'=>0.001],

        // Centrocampisti centrali (C)
        ['index'=>16, 'role_token'=>'C',   'title'=>'Slot 15: C – Top',   'level'=>'Top',  'hint'=>'Mezzala/top con bonus',                            'base_perc'=>0.080],
        ['index'=>17, 'role_token'=>'C',   'title'=>'Slot 16: C – Medio', 'level'=>'Medio','hint'=>'Altro C affidabile di fascia media',               'base_perc'=>0.036],
        ['index'=>18, 'role_token'=>'C',   'title'=>'Slot 17: C – Low',   'level'=>'Low',  'hint'=>'C low-cost di rotazione',                          'base_perc'=>0.001],

        // Trequartisti (T)
        ['index'=>19, 'role_token'=>'T',   'title'=>'Slot 18: T – Top',   'level'=>'Top',  'hint'=>'Trequartista top, raro e da bonus',                'base_perc'=>0.128],
        ['index'=>20, 'role_token'=>'T',   'title'=>'Slot 19: T – Low (multi)','level'=>'Low','hint'=>'T di riserva/multi-ruolo economico','base_perc'=>0.003],

        // Ali (W)
        ['index'=>21, 'role_token'=>'W',   'title'=>'Slot 20: W – Medio', 'level'=>'Medio','hint'=>'Ala titolare di fascia media',                     'base_perc'=>0.052],

        // Attaccanti di raccordo (A)
        ['index'=>22, 'role_token'=>'A',   'title'=>'Slot 21: A – Top',   'level'=>'Top',  'hint'=>'Seconda punta di prima fascia',                    'base_perc'=>0.171],
        ['index'=>23, 'role_token'=>'A',   'title'=>'Slot 22: A – Medio', 'level'=>'Medio','hint'=>'Seconda punta di livello medio',                   'base_perc'=>0.076],

        // Punte centrali (Pc)
        ['index'=>24, 'role_token'=>'Pc',  'title'=>'Slot 23: PC – Top',  'level'=>'Top',  'hint'=>'Centravanti titolare, prima punta top',            'base_perc'=>0.224],
        ['index'=>25, 'role_token'=>'Pc',  'title'=>'Slot 24: PC – Low/vice','level'=>'Low','hint'=>'Vice PC o giovane low-cost',                      'base_perc'=>0.001],
    ];

    // --- STATO ATTUALE -------------------------------------------------------
    $spentTotal     = \App\Models\FantaRosa::sum('costo');
    $remainingTotal = max(0, $teamBudget - $spentTotal);

    // Già assegnati: mappo per slot_index
    $assignedRows = \App\Models\FantaRosa::orderBy('slot_index')->get([
        'slot_index','external_id','nome','squadra','costo','ruolo_esteso','classic_role'
    ]);
    $assignedByIndex = [];
    foreach ($assignedRows as $r) {
        $assignedByIndex[(int)$r->slot_index] = [
            'ext_id' => $r->external_id,
            'nome'   => $r->nome,
            'team'   => $r->squadra,
            'roles'  => $r->ruolo_esteso,
            'costo'  => (int)$r->costo,
            // 'classic_role' contiene il role_token dello slot
        ];
    }

    // --- RINORMALIZZAZIONE SUGLI SLOT APERTI --------------------------------
    // Sommo le percentuali base SOLO sugli slot non ancora assegnati
    $sumOpen = 0.0;
    foreach ($slots as $s) {
        if (!isset($assignedByIndex[$s['index']])) {
            $sumOpen += (float)$s['base_perc'];
        }
    }
    $sumOpen = $sumOpen > 0 ? $sumOpen : 1.0;

    // Calcolo suggerito per OGNI slot (aperto = percentuale_rinorm * Rimanente, chiuso = 0)
    foreach ($slots as &$s) {
        if (!isset($assignedByIndex[$s['index']])) {
            $ratio = (float)$s['base_perc'] / $sumOpen;
            $s['suggested'] = (int) round($remainingTotal * $ratio);
        } else {
            $s['suggested'] = 0;
        }
    }
    unset($s);

    $team = [
        'name'      => $teamName,
        'budget'    => $teamBudget,
        'spent'     => $spentTotal,
        'remaining' => $remainingTotal,
    ];

    // Passo tutto alla view
    return view('fantacalcio.rosa', compact('team','slots','assignedByIndex'));
}



public function rosaPlayers(Request $request)
{
    // Ora filtriamo per "role_token" esatto (Por, Dc, Ds, Dd, E, M, C, T, W, A, Pc)
    $roleToken = trim((string)$request->query('role_token', ''));
    $q         = trim((string)$request->query('q', ''));

    $validTokens = ['Por','Dc','Ds','Dd','E','M','C','T','W','A','Pc'];
    if ($roleToken !== '' && !in_array($roleToken, $validTokens, true)) {
        return response()->json([]); // token non valido -> nessun risultato
    }

    $players = FantaListone::query()->where('stato', 0);

    if ($roleToken !== '') {
        $safe = preg_quote($roleToken, '/');
        $pattern = "(^|;\\s*){$safe}(\\s*;|$)";
        $players->whereRaw("ruolo_esteso REGEXP ?", [$pattern]);
    }

    if ($q !== '') {
        $players->where('nome', 'like', "%{$q}%");
    }

    $players = $players
        ->orderBy('ruolo_esteso')
        ->orderBy('squadra')
        ->orderBy('nome')
        ->limit(200)
        ->get(['external_id','ruolo_esteso','nome','squadra','fvm']);

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
    // role_token delloslot, non più "classic P/D/C/A"
    $v = Validator::make($request->all(), [
        'external_id' => ['required','integer','exists:fanta_listone,external_id','unique:fanta_rosa,external_id'],
        'costo'       => ['required','integer','min:0'],
        'role_token'  => ['required','in:Por,Dc,Ds,Dd,E,M,C,T,W,A,Pc'],
        'slot_index'  => ['required','integer','min:0',
            \Illuminate\Validation\Rule::unique('fanta_rosa','slot_index')
        ],
    ], [
        'external_id.unique' => 'Questo giocatore è già in rosa.',
        'slot_index.unique'  => 'Questo slot è già occupato.',
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

    // Verifica compatibilità: role_token deve essere presente come token in ruolo_esteso (con ;)
    $safe = preg_quote($request->role_token, '/');
    if (!preg_match("/(^|;\\s*){$safe}(\\s*;|$)/", $player->ruolo_esteso)) {
        return back()->with('error', 'Il giocatore non è compatibile con il ruolo dello slot.');
    }

    // Inserisco in rosa (riuso 'classic_role' per salvare il token dello slot)
    FantaRosa::create([
        'external_id'  => $player->external_id,
        'ruolo_esteso' => $player->ruolo_esteso,
        'nome'         => $player->nome,
        'squadra'      => $player->squadra,
        'costo'        => (int)$request->costo,
        'classic_role' => $request->role_token, // <- salvo il token slot qui per evitare migrazioni
        'slot_index'   => (int)$request->slot_index,
    ]);

    // Marca assegnato nel listone
    $player->update(['stato' => 1]);

    return back()->with('success', 'Giocatore aggiunto alla rosa.');
}


public function titolareUpdate(Request $request, $id)
{
    /** @var \App\Models\FantaListone $p */
    $p = \App\Models\FantaListone::findOrFail($id);

    // Permettiamo sia delta che set assoluto
    $delta = $request->input('delta');   // es. +1 o -1
    $value = $request->input('value');   // es. 72

    if ($value !== null && $value !== '') {
        $new = (int) $value;
    } else {
        $current = (int) ($p->titolare ?? 0);
        $new = $current + (int) $delta;
    }

    // clamp 0..100
    if ($new < 0)   $new = 0;
    if ($new > 100) $new = 100;

    $p->titolare = $new;
    $p->save();

    return response()->json([
        'ok'      => true,
        'value'   => (int) $p->titolare,
        'message' => 'Aggiornato',
    ]);
}




}
