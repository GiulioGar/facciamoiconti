<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FantaQuotazione;
use App\Models\FantaListone;
use App\Models\FantaRosa;
use App\Models\FantaBudgetState;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class FantacalcioController extends Controller
{
    private function getRosaBudget(): int
    {
        $state = FantaBudgetState::query()->first();

        if ($state && (int) $state->anchor_remaining > 0) {
            return (int) $state->anchor_remaining;
        }

        return 2500;
    }

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
    $draw   = (int) $request->get('draw', 1);
    $start  = (int) $request->get('start', 0);
    $length = (int) $request->get('length', 10);

    $name        = trim((string) $request->get('name', ''));
    $roleClassic = strtoupper(trim((string) $request->get('role_classic', '')));

    $query = \App\Models\FantaListone::query();

    if ($name !== '') {
        $query->where('nome', 'like', "%{$name}%");
    }

    if (in_array($roleClassic, ['P', 'D', 'C', 'A'], true)) {
        $query->where('ruolo', $roleClassic);
    }

    $avgSub = "(SELECT AVG(m2.mv24) FROM fanta_listone m2 WHERE m2.ruolo = fanta_listone.ruolo AND m2.mv24 IS NOT NULL)";
    $mvEffExpr = "COALESCE(fanta_listone.mv24, {$avgSub}, 1.0)";
    $likesSigned = "CAST(COALESCE(fanta_listone.`like`, 0) AS SIGNED)";
    $dislikesSigned = "CAST(COALESCE(fanta_listone.`dislike`, 0) AS SIGNED)";
    $scoreExpr = "(fanta_listone.fvm * {$mvEffExpr}) + (({$likesSigned} * 5) - {$dislikesSigned} * 5)";

    $recordsTotal = \App\Models\FantaListone::count();
    $recordsFiltered = (clone $query)->count();
    $order = $request->input('order', []);

    $columns = [
        0  => 'stato',
        1  => 'external_id',
        2  => 'ruolo',
        3  => 'nome',
        4  => 'squadra',
        5  => 'fvm',
        6  => 'titolare',
        7  => DB::raw($mvEffExpr),
        8  => DB::raw($likesSigned),
        9  => DB::raw($dislikesSigned),
        10 => DB::raw($scoreExpr),
        11 => 'level',
        12 => 'recommended_credits',
    ];

    if (!empty($order)) {
        foreach ($order as $ord) {
            $idx = (int) ($ord['column'] ?? 0);
            $dir = (($ord['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
            $col = $columns[$idx] ?? 'ruolo';

            if ($col instanceof \Illuminate\Database\Query\Expression) {
                $query->orderByRaw($col->getValue() . ' ' . $dir);
            } else {
                $query->orderBy($col, $dir);
            }
        }
    } else {
        $query->orderByRaw($scoreExpr . ' DESC')
            ->orderByRaw($likesSigned . ' DESC')
            ->orderBy('titolare', 'asc')
            ->orderBy('nome', 'asc');
    }

    $rows = $query
        ->skip($start)
        ->take($length)
        ->select([
            'id',
            'external_id',
            'ruolo',
            'nome',
            'squadra',
            'fvm',
            'titolare',
            'stato',
            DB::raw('`like` as likes'),
            DB::raw('`dislike` as dislikes'),
            'mv24',
            DB::raw("{$scoreExpr} as score_calc"),
            'level',
            'recommended_credits',
        ])
        ->get();

    $data = $rows->map(function ($r) {
        $mv24Display = $r->mv24 === null
            ? 'N.D.'
            : number_format((float) $r->mv24, 2, '.', '');

        return [
            (int) $r->stato,
            $r->external_id,
            $r->ruolo,
            $r->nome,
            $r->squadra,
            (string) (int) round($r->fvm),
            $r->titolare === null ? null : (int) $r->titolare,
            $mv24Display,
            (int) $r->likes,
            (int) $r->dislikes,
            number_format((float) $r->score_calc, 2, '.', ''),
            (int) ($r->level ?? 3),
            $r->recommended_credits ?? '-',
            (int) $r->id,
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
        return response()->json(['ok' => false, 'message' => 'Il valore non puÃ² scendere sotto zero'], 422);
    }
    $p->decrement('like');
    return response()->json(['ok' => true, 'like' => (int)$p->like]);
}

public function decrementDislike($id)
{
    $p = \App\Models\FantaListone::findOrFail($id);
    if ($p->dislike <= 0) {
        return response()->json(['ok' => false, 'message' => 'Il valore non puÃ² scendere sotto zero'], 422);
    }
    $p->decrement('dislike');
    return response()->json(['ok' => true, 'dislike' => (int)$p->dislike]);
}


public function rosa()
{
    $teamName   = 'Azzurlions';
    $teamBudget = $this->getRosaBudget();

    $goalkeeperSlots = array_map(function (array $slot) {
        return array_merge($slot, [
            'role_token' => 'P',
            'title' => 'Portiere ' . ((int) $slot['index'] + 1),
            'level' => $slot['index'] === 0 ? 'Top' : 'Low',
            'hint' => 'Slot tecnico portieri, predisposto per futuri blocchi/treni.',
            'base_perc' => $slot['strategic_weight'],
        ]);
    }, config('fantacalcio.rosa_goalkeeper_slots', []));

    $slots = array_merge($goalkeeperSlots, [
        ['index'=>6,  'role_token'=>'D', 'title'=>'Slot 1: Difensore',        'level'=>'Top',   'hint'=>'Difensore di prima fascia, titolare.', 'base_perc'=>0.028],
        ['index'=>7,  'role_token'=>'D', 'title'=>'Slot 2: Difensore',        'level'=>'Medio', 'hint'=>'Difensore affidabile di fascia media.', 'base_perc'=>0.018],
        ['index'=>8,  'role_token'=>'D', 'title'=>'Slot 3: Difensore',        'level'=>'Low',   'hint'=>'Difensore low-cost ma con spazio.', 'base_perc'=>0.001],
        ['index'=>9,  'role_token'=>'D', 'title'=>'Slot 4: Difensore',        'level'=>'Low',   'hint'=>'Difensore di riserva low-cost.', 'base_perc'=>0.001],
        ['index'=>10, 'role_token'=>'D', 'title'=>'Slot 5: Difensore',        'level'=>'Low',   'hint'=>'Difensore di copertura.', 'base_perc'=>0.001],
        ['index'=>11, 'role_token'=>'D', 'title'=>'Slot 6: Difensore',        'level'=>'Medio', 'hint'=>'Difensore titolare di buon livello.', 'base_perc'=>0.032],
        ['index'=>12, 'role_token'=>'D', 'title'=>'Slot 7: Difensore',        'level'=>'Low',   'hint'=>'Vice difensore economico.', 'base_perc'=>0.001],
        ['index'=>13, 'role_token'=>'D', 'title'=>'Slot 8: Difensore',        'level'=>'Medio', 'hint'=>'Altro difensore titolare di fascia media.', 'base_perc'=>0.036],
        ['index'=>14, 'role_token'=>'C', 'title'=>'Slot 1: Centrocampista',   'level'=>'Medio', 'hint'=>'Centrocampista titolare di buon livello.', 'base_perc'=>0.030],
        ['index'=>15, 'role_token'=>'C', 'title'=>'Slot 2: Centrocampista',   'level'=>'Low',   'hint'=>'Centrocampista economico.', 'base_perc'=>0.001],
        ['index'=>16, 'role_token'=>'C', 'title'=>'Slot 3: Centrocampista',   'level'=>'Low',   'hint'=>'Jolly di centrocampo low-cost.', 'base_perc'=>0.001],
        ['index'=>17, 'role_token'=>'C', 'title'=>'Slot 4: Centrocampista',   'level'=>'Medio', 'hint'=>'Centrocampista equilibrato.', 'base_perc'=>0.026],
        ['index'=>18, 'role_token'=>'C', 'title'=>'Slot 5: Centrocampista',   'level'=>'Low',   'hint'=>'Centrocampista di scorta.', 'base_perc'=>0.001],
        ['index'=>19, 'role_token'=>'C', 'title'=>'Slot 6: Centrocampista',   'level'=>'Top',   'hint'=>'Centrocampista top con bonus.', 'base_perc'=>0.080],
        ['index'=>20, 'role_token'=>'C', 'title'=>'Slot 7: Centrocampista',   'level'=>'Medio', 'hint'=>'Altro centrocampista affidabile.', 'base_perc'=>0.036],
        ['index'=>21, 'role_token'=>'C', 'title'=>'Slot 8: Centrocampista',   'level'=>'Low',   'hint'=>'Centrocampista di rotazione.', 'base_perc'=>0.001],
        ['index'=>22, 'role_token'=>'A', 'title'=>'Slot 1: Attaccante',       'level'=>'Top',   'hint'=>'Attaccante di prima fascia.', 'base_perc'=>0.171],
        ['index'=>23, 'role_token'=>'A', 'title'=>'Slot 2: Attaccante',       'level'=>'Medio', 'hint'=>'Attaccante di livello medio.', 'base_perc'=>0.076],
        ['index'=>24, 'role_token'=>'A', 'title'=>'Slot 3: Attaccante',       'level'=>'Top',   'hint'=>'Prima punta top.', 'base_perc'=>0.224],
        ['index'=>25, 'role_token'=>'A', 'title'=>'Slot 4: Attaccante',       'level'=>'Low',   'hint'=>'Vice attaccante o scommessa.', 'base_perc'=>0.001],
        ['index'=>26, 'role_token'=>'A', 'title'=>'Slot 5: Attaccante',       'level'=>'Low',   'hint'=>'Attaccante di completamento rosa.', 'base_perc'=>0.001],
        ['index'=>27, 'role_token'=>'A', 'title'=>'Slot 6: Attaccante',       'level'=>'Low',   'hint'=>'Ultimo slot offensivo low-cost.', 'base_perc'=>0.001],
    ]);

    $spentTotal     = FantaRosa::sum('costo');
    $remainingTotal = max(0, $teamBudget - $spentTotal);

    $assignedRows = FantaRosa::orderBy('slot_index')->get([
        'slot_index', 'external_id', 'nome', 'squadra', 'costo', 'ruolo_esteso', 'classic_role'
    ]);
    $assignedByIndex = [];
    foreach ($assignedRows as $r) {
        $assignedByIndex[(int) $r->slot_index] = [
            'ext_id'       => $r->external_id,
            'nome'         => $r->nome,
            'team'         => $r->squadra,
            'roles'        => $r->ruolo_esteso,
            'classic_role' => $r->classic_role,
            'costo'        => (int) $r->costo,
        ];
    }

    $sumOpen = 0.0;
    foreach ($slots as $s) {
        if (!isset($assignedByIndex[$s['index']])) {
            $sumOpen += (float) $s['base_perc'];
        }
    }
    $sumOpen = $sumOpen > 0 ? $sumOpen : 1.0;

    foreach ($slots as &$s) {
        if (!isset($assignedByIndex[$s['index']])) {
            $ratio = (float) $s['base_perc'] / $sumOpen;
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

    return view('fantacalcio.rosa', compact('team', 'slots', 'assignedByIndex'));
}

public function rosaPlayers(Request $request)
{
    $roleToken = trim((string) $request->query('role_token', ''));
    $q         = trim((string) $request->query('q', ''));

    $validTokens = ['P', 'D', 'C', 'A'];
    if ($roleToken !== '' && !in_array($roleToken, $validTokens, true)) {
        return response()->json([]);
    }

    $avgSub         = "(SELECT AVG(m2.mv24) FROM fanta_listone m2 WHERE m2.ruolo = fanta_listone.ruolo AND m2.mv24 IS NOT NULL)";
    $mvEffExpr      = "COALESCE(fanta_listone.mv24, {$avgSub}, 1.0)";
    $likesSigned    = "CAST(COALESCE(fanta_listone.`like`, 0) AS SIGNED)";
    $dislikesSigned = "CAST(COALESCE(fanta_listone.`dislike`, 0) AS SIGNED)";
    $scoreExpr      = "(fanta_listone.fvm * {$mvEffExpr}) + (({$likesSigned} * 5) - ({$dislikesSigned} * 5))";

    $players = FantaListone::query()
        ->where('stato', 0);

    if ($roleToken !== '') {
        $players->where('ruolo', $roleToken);
    }

    if ($q !== '') {
        $players->where('nome', 'like', "%{$q}%");
    }

    $players = $players
        ->select([
            'external_id',
            'ruolo',
            'ruolo_esteso',
            'nome',
            'squadra',
            'fvm',
            DB::raw("{$scoreExpr} as score_calc"),
        ])
        ->orderByDesc('score_calc')
        ->limit(200)
        ->get();

    $data = $players->map(function ($p) {
        return [
            'value' => $p->external_id,
            'text' => "{$p->nome} ({$p->squadra}) - {$p->ruolo} - Score " . number_format($p->score_calc, 1),
        ];
    });

    return response()->json($data);
}

public function rosaAdd(Request $request)
{
    $v = Validator::make($request->all(), [
        'external_id' => ['required','integer','exists:fanta_listone,external_id','unique:fanta_rosa,external_id'],
        'costo'       => ['required','integer','min:0'],
        'role_token'  => ['required','in:P,D,C,A'],
        'slot_index'  => ['required','integer','min:0', Rule::unique('fanta_rosa','slot_index')],
    ], [
        'external_id.unique' => 'Questo giocatore Ã¨ giÃ  in rosa.',
        'slot_index.unique'  => 'Questo slot Ã¨ giÃ  occupato.',
    ]);
    if ($v->fails()) {
        return back()->withErrors($v)->with('error', 'Dati non validi.');
    }

    $teamBudget = $this->getRosaBudget();
    $spentTotal = FantaRosa::sum('costo');
    $remaining  = $teamBudget - $spentTotal;

    if ((int) $request->costo > $remaining) {
        return back()->with('error', 'Acquisto non consentito: crediti insufficienti.');
    }

    $player = FantaListone::where('external_id', $request->external_id)
        ->where('stato', 0)
        ->firstOrFail();

    if ($player->ruolo !== $request->role_token) {
        return back()->with('error', 'Il giocatore non Ã¨ compatibile con il ruolo dello slot.');
    }

    FantaRosa::create([
        'external_id'  => $player->external_id,
        'ruolo_esteso' => $player->ruolo_esteso,
        'nome'         => $player->nome,
        'squadra'      => $player->squadra,
        'costo'        => (int) $request->costo,
        'classic_role' => $request->role_token,
        'slot_index'   => (int) $request->slot_index,
    ]);

    $player->update(['stato' => 1]);

    return back()->with('success', 'Giocatore aggiunto alla rosa.');
}

public function rosaReset()
{
    $externalIds = FantaRosa::query()->pluck('external_id');

    DB::beginTransaction();
    try {
        if ($externalIds->isNotEmpty()) {
            FantaListone::query()
                ->whereIn('external_id', $externalIds)
                ->update(['stato' => 0, 'updated_at' => now()]);
        }

        FantaRosa::query()->delete();

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with('error', 'Errore durante azzeramento rosa: ' . $e->getMessage());
    }

    return back()->with('success', 'Rosa azzerata.');
}

public function rosaBudgetUpdate(Request $request)
{
    $v = Validator::make($request->all(), [
        'budget' => ['required', 'integer', 'min:1', 'max:9999'],
    ]);

    if ($v->fails()) {
        return back()->withErrors($v)->with('error', 'Budget non valido.');
    }

    $state = FantaBudgetState::query()->first();

    FantaBudgetState::query()->updateOrCreate(
        ['id' => optional($state)->id ?? 1],
        [
            'anchor_remaining' => (int) $request->budget,
            'caps' => optional($state)->caps ?? [],
            'spent_at_anchor' => optional($state)->spent_at_anchor ?? [],
            'open_roles' => optional($state)->open_roles ?? [],
        ]
    );

    return back()->with('success', 'Budget aggiornato.');
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


public function updateLevel(Request $request, $id)
{
    $v = Validator::make($request->all(), [
        'level' => ['required','integer','min:1','max:5'],
    ]);
    if ($v->fails()) {
        return response()->json(['ok'=>false,'message'=>'Level non valido (1..5)'], 422);
    }

    /** @var FantaListone $p */
    $p = FantaListone::findOrFail((int)$id);
    $lvl = (int)$request->level;

    // budget per reparto in base al ruolo classic del giocatore
    $roleBudget = ['P'=>120, 'D'=>300, 'C'=>900, 'A'=>1180];
    $levelPerc  = [5=>0.50, 4=>0.15, 3=>0.05, 2=>0.01, 1=>0.00];

    $budget = $roleBudget[$p->ruolo] ?? 0;
    if ($lvl === 1) {
        $credits = 1;
    } else {
        $credits = ($budget > 0) ? (int)floor($budget * ($levelPerc[$lvl] ?? 0.0)) : 0;
        $credits = max(1, $credits);
    }
    $credits = min(2500, $credits);

    $p->level = $lvl;
    $p->recommended_credits = $credits;
    $p->save();

    return response()->json(['ok'=>true,'level'=>$p->level,'recommended_credits'=>$p->recommended_credits]);
}


// == CALCOLO AUTOMATICO LIVELLI ==
public function updateLevels(Request $request)
{
    // ðŸ‘‰ allinea la formula a quella che usi altrove (like*5 âˆ’ dislike*5)
    $avgSub         = "(SELECT AVG(m2.mv24) FROM fanta_listone m2 WHERE m2.ruolo = fanta_listone.ruolo AND m2.mv24 IS NOT NULL)";
    $mvEffExpr      = "COALESCE(fanta_listone.mv24, {$avgSub}, 1.0)";
    $likesSigned    = "CAST(COALESCE(fanta_listone.`like`, 0) AS SIGNED)";
    $dislikesSigned = "CAST(COALESCE(fanta_listone.`dislike`, 0) AS SIGNED)";
    $scoreExpr      = "(fanta_listone.fvm * {$mvEffExpr}) + (({$likesSigned} * 5) - ({$dislikesSigned} * 5))";

    // Budget per reparto (Classic role)
    $roleBudget = ['P'=>120, 'D'=>300, 'C'=>900, 'A'=>1180];
    // Percentuali per level
    $levelPerc = [5=>0.50, 4=>0.15, 3=>0.05, 2=>0.01, 1=>0.00];

    // prendo id, ruolo classic e score
    $rows = FantaListone::query()
        ->select(['id','ruolo', DB::raw("{$scoreExpr} as score_calc")])
        ->get();

    if ($rows->isEmpty()) {
        return back()->with('error', 'Nessun dato per il calcolo livelli.');
    }

    $byRole = $rows->groupBy('ruolo');

    $levelCase  = "CASE id ";
    $creditCase = "CASE id ";
    $ids        = [];

    foreach ($byRole as $role => $items) {
        $sorted = $items->sortByDesc('score_calc')->values();

        // TOP 5 per ruolo -> level 5
        foreach ($sorted->take(5) as $r) {
            $lvl = 5;
            $ids[]      = (int)$r->id;
            $levelCase .= "WHEN {$r->id} THEN {$lvl} ";
            $budget     = $roleBudget[$role] ?? 0;
            $credits    = ($budget > 0) ? (int)floor($budget * $levelPerc[$lvl]) : 0;
            $credits    = max(1, min(2500, $credits));
            $creditCase .= "WHEN {$r->id} THEN {$credits} ";
        }

        // Restanti -> fasce 1..4
        $rest = $sorted->slice(5)->values();
        if ($rest->isEmpty()) continue;

        $scores = $rest->pluck('score_calc')->map(fn($v)=>(float)$v);
        $mean   = $scores->avg();
        $std    = self::stddev($scores);
        // soglie (tarabili)
        $t1 = $mean + 0.75*$std; // -> 4
        $t2 = $mean - 0.25*$std; // -> 3
        $t3 = $mean - 1.25*$std; // -> 2

        foreach ($rest as $r) {
            $s = (float)$r->score_calc;
            $lvl = 3;
            if ($std > 0) {
                if     ($s >= $t1) $lvl = 4;
                elseif ($s >= $t2) $lvl = 3;
                elseif ($s >= $t3) $lvl = 2;
                else               $lvl = 1;
            }

            $ids[]      = (int)$r->id;
            $levelCase .= "WHEN {$r->id} THEN {$lvl} ";

            $budget = $roleBudget[$role] ?? 0;
            if ($lvl === 1) {
                $credits = 1; // regola speciale
            } else {
                $credits = ($budget > 0) ? (int)floor($budget * ($levelPerc[$lvl] ?? 0.0)) : 0;
                $credits = max(1, $credits);
            }
            $credits    = min(2500, $credits);
            $creditCase .= "WHEN {$r->id} THEN {$credits} ";
        }
    }

    if (empty($ids)) return back()->with('success','Nessun aggiornamento necessario.');

    $levelCase  .= "END";
    $creditCase .= "END";

    DB::beginTransaction();
    try {
        FantaListone::whereIn('id', array_unique($ids))->update([
            'level'               => DB::raw($levelCase),
            'recommended_credits' => DB::raw($creditCase),
            'updated_at'          => now(),
        ]);
        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with('error', 'Errore aggiornando livelli/crediti: '.$e->getMessage());
    }

    return back()->with('success', 'Livelli e crediti ricalcolati con successo.');
}


public function updateCredits(Request $request, $id)
{
    $v = Validator::make($request->all(), [
        'recommended_credits' => ['nullable','integer','min:1','max:2500'],
    ]);
    if ($v->fails()) {
        return response()->json(['ok'=>false, 'message'=>'Valore crediti non valido (1..2500 o vuoto).'], 422);
    }

    $p = \App\Models\FantaListone::findOrFail((int)$id);
    $val = $request->input('recommended_credits');
    $p->recommended_credits = ($val === null || $val === '') ? null : (int)$val;
    $p->save();

    return response()->json(['ok'=>true, 'recommended_credits'=>$p->recommended_credits]);
}

/**
 * Deviazione standard campionaria (n-1) su una Collection numerica.
 */
private static function stddev(\Illuminate\Support\Collection $values): float
{
    $n = $values->count();
    if ($n <= 1) return 0.0;
    $mean = $values->avg();
    $acc  = 0.0;
    foreach ($values as $v) { $d = ((float)$v) - $mean; $acc += $d * $d; }
    return sqrt($acc / ($n - 1));
}

public function updateSlotRole(Request $request)
{
    $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'slot_index'     => ['required','integer','min:0','max:27'],
        'new_role_token' => ['required','in:P,D,C,A'],
    ], [
        'slot_index.required' => 'Slot mancante.',
        'new_role_token.in'   => 'Ruolo non valido.',
    ]);

    if ($v->fails()) {
        return back()->withErrors($v)->with('error', 'Dati non validi per aggiornare il ruolo.');
    }

    return back()->with('success', 'Ruolo slot aggiornato.');
}



}


