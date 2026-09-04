<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\FantaQuotazione;
use App\Models\FantaListone;
use App\Models\FantaRosa;
use App\Models\FantaBudgetState;
use App\Services\Fantacalcio\RosaBudgetCalculator;
use App\Services\Fantacalcio\RosaStatusEvaluator;
use App\Services\Fantacalcio\AppetibilityCalculator;
use App\Models\FantaPlayerStats;
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

    private function normalizeRosaTeam($team): string
    {
        $team = preg_replace('/\s+/u', ' ', trim((string) $team));

        return mb_strtoupper($team, 'UTF-8');
    }

    private function resolveRosaRole(FantaRosa $player): string
    {
        if ($player->classic_role !== null) {
            return $player->classic_role;
        }

        $slotIndex = (int) $player->slot_index;

        foreach (config('fantacalcio.rosa_goalkeeper_slots', []) as $slot) {
            if ((int) $slot['index'] === $slotIndex) {
                return 'P';
            }
        }

        foreach (config('fantacalcio.rosa_dca_slots', []) as $role => $slots) {
            foreach ($slots as $slot) {
                if ((int) $slot['index'] === $slotIndex) {
                    return $role;
                }
            }
        }

        $listonePlayer = FantaListone::where('external_id', $player->external_id)->first();
        if ($listonePlayer && $listonePlayer->ruolo) {
            return $listonePlayer->ruolo;
        }

        return 'D';
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

    // --- IMPORT CSV/XLSX per fanta_quotazione ---
    public function quoteImport(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        $file      = $request->file('csv');
        $path      = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = $extension === 'xlsx'
            ? $this->parseQuoteXlsx($path)
            : $this->parseQuoteCsv($path);

        if ($rows === null) {
            return back()->with('error', 'Impossibile aprire il file.');
        }

        if (is_string($rows)) {
            return back()->with('error', $rows);
        }

        if (empty($rows)) {
            return back()->with('error', 'Nessun dato valido trovato nel file.');
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

    private function parseQuoteXlsx(string $path)
    {
        $colMap = [
            'id'       => 'external_id',
            'r'        => 'ruolo',
            'rm'       => 'ruolo_esteso',
            'nome'     => 'nome',
            'squadra'  => 'squadra',
            'qt.a'     => 'quota_a',
            'qt.i'     => 'quota_i',
            'diff.'    => 'diff_quota',
            'qt.a m'   => 'quota_a_m',
            'qt.i m'   => 'quota_i_m',
            'diff.m'   => 'diff_quota_m',
            'fvm'      => 'fvm',
            'fvm m'    => 'fvm_m',
        ];
        $required = ['external_id', 'ruolo', 'ruolo_esteso', 'nome', 'squadra', 'fvm'];

        $reader = \OpenSpout\Reader\Common\Creator\ReaderFactory::createFromType('xlsx');
        $reader->open($path);

        $rows    = [];
        $idx     = [];
        $rowNum  = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowNum++;
                $cells = [];
                foreach ($row->getCells() as $cell) {
                    $v = $cell->getValue();
                    $cells[] = is_string($v) ? trim($v) : $v;
                }

                if ($rowNum === 1) continue; // riga titolo

                if ($rowNum === 2) {
                    $header = array_map(fn($v) => strtolower(trim((string) $v)), $cells);
                    foreach ($header as $pos => $label) {
                        if (isset($colMap[$label])) {
                            $idx[$colMap[$label]] = $pos;
                        }
                    }
                    foreach ($required as $field) {
                        if (!isset($idx[$field])) {
                            $reader->close();
                            return "Colonna richiesta mancante nell'XLSX: {$field}";
                        }
                    }
                    continue;
                }

                $extId = (int) (float) ($cells[$idx['external_id']] ?? 0);
                if ($extId <= 0) continue;

                $intCol = fn(string $field) => isset($idx[$field]) && isset($cells[$idx[$field]])
                    ? (int) (float) $cells[$idx[$field]]
                    : null;

                $rows[] = [
                    'external_id'  => $extId,
                    'ruolo'        => (string) ($cells[$idx['ruolo']] ?? ''),
                    'ruolo_esteso' => (string) ($cells[$idx['ruolo_esteso']] ?? ''),
                    'nome'         => (string) ($cells[$idx['nome']] ?? ''),
                    'squadra'      => (string) ($cells[$idx['squadra']] ?? ''),
                    'fvm'          => $intCol('fvm') ?? 0,
                    'quota_a'      => $intCol('quota_a'),
                    'quota_i'      => $intCol('quota_i'),
                    'diff_quota'   => $intCol('diff_quota'),
                    'quota_a_m'    => $intCol('quota_a_m'),
                    'quota_i_m'    => $intCol('quota_i_m'),
                    'diff_quota_m' => $intCol('diff_quota_m'),
                    'fvm_m'        => $intCol('fvm_m'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
            break; // solo foglio "Tutti"
        }

        $reader->close();
        return $rows;
    }

    private function parseQuoteCsv(string $path)
    {
        $handle = fopen($path, 'r');
        if (!$handle) return null;

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (strncmp($firstLine, $bom, 3) === 0) {
            fseek($handle, 3);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return 'Header CSV mancante o non valido.';
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $required = ['id', 'r', 'rm', 'nome', 'squadra', 'fvm'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                return "Colonna richiesta mancante: {$col}";
            }
        }

        $idx     = array_flip($header);
        $rows    = [];
        $convert = fn($v) => trim(mb_convert_encoding($v, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252'));
        $optInt  = fn($data, $key) => isset($idx[$key]) && isset($data[$idx[$key]])
            ? (int) $convert($data[$idx[$key]])
            : null;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) < count($header)) continue;

            $rows[] = [
                'external_id'  => (int) $convert($data[$idx['id']]),
                'ruolo'        => $convert($data[$idx['r']]),
                'ruolo_esteso' => $convert($data[$idx['rm']]),
                'nome'         => $convert($data[$idx['nome']]),
                'squadra'      => $convert($data[$idx['squadra']]),
                'fvm'          => (int) $convert($data[$idx['fvm']]),
                'quota_a'      => $optInt($data, 'qt.a'),
                'quota_i'      => $optInt($data, 'qt.i'),
                'diff_quota'   => $optInt($data, 'diff.'),
                'quota_a_m'    => $optInt($data, 'qt.a m'),
                'quota_i_m'    => $optInt($data, 'qt.i m'),
                'diff_quota_m' => $optInt($data, 'diff.m'),
                'fvm_m'        => $optInt($data, 'fvm m'),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        fclose($handle);
        return $rows;
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

        $rows = FantaQuotazione::select(
                'external_id','ruolo','ruolo_esteso','nome','squadra',
                'fvm','quota_a','quota_i','diff_quota',
                'quota_a_m','quota_i_m','diff_quota_m','fvm_m'
            )
            ->get()
            ->map(fn($r) => [
                'external_id'  => $r->external_id,
                'ruolo'        => $r->ruolo,
                'ruolo_esteso' => $r->ruolo_esteso,
                'nome'         => $r->nome,
                'squadra'      => $r->squadra,
                'fvm'          => $r->fvm,
                'quota_a'      => $r->quota_a,
                'quota_i'      => $r->quota_i,
                'diff_quota'   => $r->diff_quota,
                'quota_a_m'    => $r->quota_a_m,
                'quota_i_m'    => $r->quota_i_m,
                'diff_quota_m' => $r->diff_quota_m,
                'fvm_m'        => $r->fvm_m,
                'like'         => 0,
                'dislike'      => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ])
            ->toArray();

        $validIds = FantaQuotazione::pluck('external_id')->toArray();

        DB::beginTransaction();
        try {
            // Nuovi: inserisce tutta la riga. Esistenti: aggiorna quote + fvm + azzera like/dislike stagione precedente
            DB::table('fanta_listone')->upsert(
                $rows,
                ['external_id'],
                ['nome','ruolo','ruolo_esteso','squadra','fvm','quota_a','quota_i','diff_quota','quota_a_m','quota_i_m','diff_quota_m','fvm_m','like','dislike','updated_at']
            );
            // Rimuove giocatori non piu' presenti nelle quotazioni correnti
            $removed = FantaListone::whereNotIn('external_id', $validIds)->count();
            FantaListone::whereNotIn('external_id', $validIds)->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore durante aggiornamento listone: ' . $e->getMessage());
        }

        return back()->with('success', "Lista aggiornata: inseriti ".count($toInsertIds).", aggiornati ".count($toUpdateIds).", rimossi {$removed}.");
    }

public function listoneData(Request $request)
{
    $draw   = (int) $request->get('draw', 1);
    $start  = (int) $request->get('start', 0);
    $length = (int) $request->get('length', 10);

    $name        = trim((string) $request->get('name', ''));
    $roleClassic = strtoupper(trim((string) $request->get('role_classic', '')));
    $levelFilter = (int) $request->get('level', 0);

    $query = \App\Models\FantaListone::query();

    if ($name !== '') {
        $query->where('nome', 'like', "%{$name}%");
    }

    if (in_array($roleClassic, ['P', 'D', 'C', 'A'], true)) {
        $query->where('ruolo', $roleClassic);
    }

    if ($levelFilter >= 1 && $levelFilter <= 5) {
        $query->where('level', $levelFilter);
    }

    $likesSigned    = "CAST(COALESCE(fanta_listone.`like`, 0) AS SIGNED)";
    $dislikesSigned = "CAST(COALESCE(fanta_listone.`dislike`, 0) AS SIGNED)";

    $recordsTotal    = \App\Models\FantaListone::count();
    $recordsFiltered = (clone $query)->count();
    $order           = $request->input('order', []);

    $columns = [
        0  => 'stato',
        1  => 'ruolo',
        2  => 'nome',
        3  => 'squadra',
        4  => 'fvm',
        5  => 'titolare',
        6  => 'score',
        7  => 'level',
        8  => 'ia',
        9  => 'fanta_fascia',
        10 => DB::raw($likesSigned),
        11 => DB::raw($dislikesSigned),
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
        $query->orderBy('score', 'desc')
            ->orderByRaw($likesSigned . ' DESC')
            ->orderBy('nome', 'asc');
    }

    $rows = $query
        ->skip($start)
        ->take($length)
        ->select([
            'id',
            'ruolo',
            'nome',
            'squadra',
            'fvm',
            'titolare',
            'stato',
            DB::raw('`like` as likes'),
            DB::raw('`dislike` as dislikes'),
            'score',
            'level',
            'ia',
            'fanta_fascia',
        ])
        ->get();

    $data = $rows->map(function ($r) {
        return [
            (int) $r->stato,                                                          // 0  - Asta
            $r->ruolo,                                                                // 1  - Ruolo
            $r->nome,                                                                 // 2  - Nome
            $r->squadra,                                                              // 3  - Squadra
            (string) (int) round($r->fvm),                                            // 4  - FVM
            $r->titolare === null ? null : (int) $r->titolare,                        // 5  - Titolare
            $r->score !== null ? number_format((float) $r->score, 2, '.', '') : null, // 6  - Score
            (int) ($r->level ?? 3),                                                   // 7  - Level
            $r->ia !== null ? (int) $r->ia : null,                                    // 8  - IA
            $r->fanta_fascia !== null ? (int) $r->fanta_fascia : null,                // 9  - Fascia
            (int) $r->likes,                                                          // 10 - Like
            (int) $r->dislikes,                                                       // 11 - Dislike
            (int) $r->id,                                                             // 12 - hidden id
        ];
    });

    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}

public function incrementLike($id)
{
    $p = FantaListone::findOrFail($id);
    if ($p->like >= 1000) {
        return response()->json(['ok' => false, 'message' => 'Limite massimo raggiunto'], 422);
    }
    $oldLike = (int) $p->like; $oldDislike = (int) $p->dislike;
    $p->increment('like');
    $this->refreshScore($p, $oldLike, $oldDislike);
    return response()->json(['ok' => true, 'like' => (int) $p->like]);
}

public function decrementLike($id)
{
    $p = FantaListone::findOrFail($id);
    if ($p->like <= 0) {
        return response()->json(['ok' => false, 'message' => 'Il valore non può scendere sotto zero'], 422);
    }
    $oldLike = (int) $p->like; $oldDislike = (int) $p->dislike;
    $p->decrement('like');
    $this->refreshScore($p, $oldLike, $oldDislike);
    return response()->json(['ok' => true, 'like' => (int) $p->like]);
}

public function incrementDislike($id)
{
    $p = FantaListone::findOrFail($id);
    if ($p->dislike >= 1000) {
        return response()->json(['ok' => false, 'message' => 'Limite massimo raggiunto'], 422);
    }
    $oldLike = (int) $p->like; $oldDislike = (int) $p->dislike;
    $p->increment('dislike');
    $this->refreshScore($p, $oldLike, $oldDislike);
    return response()->json(['ok' => true, 'dislike' => (int) $p->dislike]);
}

public function decrementDislike($id)
{
    $p = FantaListone::findOrFail($id);
    if ($p->dislike <= 0) {
        return response()->json(['ok' => false, 'message' => 'Il valore non può scendere sotto zero'], 422);
    }
    $oldLike = (int) $p->like; $oldDislike = (int) $p->dislike;
    $p->decrement('dislike');
    $this->refreshScore($p, $oldLike, $oldDislike);
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

// Ricalcola la componente C dello score quando like/dislike cambiano.
// Non tocca score se è null (giocatore senza statistiche).
private function refreshScore(FantaListone $p, int $oldLike, int $oldDislike): void
{
    if ($p->score === null) return;

    $cOld     = max(-1.0, min(1.0, ($oldLike - $oldDislike) / 10.0)) * 10.0;
    $cNew     = max(-1.0, min(1.0, ((int) $p->like - (int) $p->dislike) / 10.0)) * 10.0;
    $newScore = round(max(0.0, min(100.0, (float) $p->score - $cOld + $cNew)), 2);

    $p->score = $newScore;
    $p->level = $this->scoreToLevel($newScore);
    $p->save();
}

// Soglie floor per il level — usa i floor assoluti (non i percentili di ruolo,
// che richiedono la distribuzione completa e non sono pratici per un aggiornamento live).
private function scoreToLevel(float $score): int
{
    if ($score >= 72) return 5;
    if ($score >= 65) return 4;
    if ($score >= 55) return 3;
    if ($score >= 45) return 2;
    return 1;
}


public function rosa()
{
    $teamName   = 'Azzurlions';
    $teamBudget = $this->getRosaBudget();
    $slots = $this->buildRosaSlots();
    $assignedByIndex = $this->loadAssignedByIndex();

    $budgetResult = app(RosaBudgetCalculator::class)
        ->calculate($teamBudget, $slots, $assignedByIndex);
    $slots = $budgetResult['slots'];

    $strategyStatus = app(RosaStatusEvaluator::class)
        ->evaluate($teamBudget, $slots, $budgetResult, $assignedByIndex);

    $team = [
        'name'      => $teamName,
        'budget'    => $teamBudget,
        'spent'     => $budgetResult['spent'],
        'remaining' => $budgetResult['remaining'],
        'completion_floor' => $budgetResult['completion_floor'],
        'strategic_budget' => $budgetResult['strategic_budget'],
        'strategic_budget_dca' => $budgetResult['strategic_budget_dca'],
        'goalkeeper' => $budgetResult['goalkeeper'],
        'roles' => $budgetResult['roles'],
    ];

    return view('fantacalcio.rosa', compact('team', 'slots', 'assignedByIndex', 'strategyStatus'));
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

    $goalkeeperTeams = [];
    if ($roleToken === 'P') {
        $goalkeeperTeams = FantaRosa::query()
            ->where('classic_role', 'P')
            ->pluck('squadra')
            ->map(function ($team) {
                return $this->normalizeRosaTeam($team);
            })
            ->unique()
            ->values()
            ->all();
    }

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

    $data = $players->map(function ($p) use ($roleToken, $goalkeeperTeams) {
        return [
            'value' => $p->external_id,
            'text' => "{$p->nome} ({$p->squadra}) - {$p->ruolo} - Score " . number_format($p->score_calc, 1),
            'is_cover' => $roleToken === 'P'
                && in_array($this->normalizeRosaTeam($p->squadra), $goalkeeperTeams, true),
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

    $player = FantaListone::where('external_id', $request->external_id)
        ->where('stato', 0)
        ->firstOrFail();

    if ($player->ruolo !== $request->role_token) {
        return back()->with('error', 'Il giocatore non Ã¨ compatibile con il ruolo dello slot.');
    }

    $cost = (int) $request->costo;
    if ($request->role_token === 'P') {
        $teamKey = $this->normalizeRosaTeam($player->squadra);
        $hasTeamGoalkeeper = FantaRosa::query()
            ->where('classic_role', 'P')
            ->get(['squadra'])
            ->contains(function ($rosaPlayer) use ($teamKey) {
                return $this->normalizeRosaTeam($rosaPlayer->squadra) === $teamKey;
            });

        if ($hasTeamGoalkeeper) {
            $cost = 0;
        }
    }

    $teamBudget = $this->getRosaBudget();
    $spentTotal = FantaRosa::sum('costo');
    $remaining  = $teamBudget - $spentTotal;

    if ($cost > $remaining) {
        return back()->with('error', 'Acquisto non consentito: crediti insufficienti.');
    }

    $targetSnapshot = null;
    $massimoSnapshot = null;

    if ($request->role_token !== 'P') {
        $budgetResult = app(RosaBudgetCalculator::class)
            ->calculate($teamBudget, $this->buildRosaSlots(), $this->loadAssignedByIndex());
        $requestedSlotIndex = (int) $request->slot_index;
        $plannerSlot = collect($budgetResult['slots'])->first(function ($slot) use ($requestedSlotIndex, $request) {
            return (int) ($slot['index'] ?? -1) === $requestedSlotIndex
                && ($slot['role_token'] ?? null) === $request->role_token;
        });

        if (!$plannerSlot || !array_key_exists('target', $plannerSlot) || !array_key_exists('massimo', $plannerSlot)) {
            return back()->with('error', 'Impossibile determinare target e massimo dello slot selezionato.');
        }

        $targetSnapshot = $plannerSlot['target'];
        $massimoSnapshot = $plannerSlot['massimo'];

        if ($targetSnapshot === null || $massimoSnapshot === null) {
            return back()->with('error', 'Dati strategici mancanti per lo slot selezionato.');
        }
    }

    FantaRosa::create([
        'external_id'  => $player->external_id,
        'ruolo_esteso' => $player->ruolo_esteso,
        'nome'         => $player->nome,
        'squadra'      => $player->squadra,
        'costo'        => $cost,
        'target_snapshot' => $targetSnapshot,
        'massimo_snapshot' => $massimoSnapshot,
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

public function rosaRemove(Request $request)
{
    $v = Validator::make($request->all(), [
        'external_id' => ['required', 'integer'],
    ]);

    if ($v->fails()) {
        return back()->withErrors($v)->with('error', 'Dati non validi.');
    }

    $externalId = (int) $request->external_id;
    $errorMessage = null;

    try {
        DB::transaction(function () use ($externalId, &$errorMessage) {
            $rosaPlayer = FantaRosa::where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if (!$rosaPlayer) {
                $errorMessage = 'Giocatore non trovato in rosa.';
                return;
            }

            $role = $this->resolveRosaRole($rosaPlayer);

            if ($role !== 'P') {
                $rosaPlayer->delete();
                FantaListone::where('external_id', $externalId)->update(['stato' => 0]);
                return;
            }

            $mainCosto = (int) $rosaPlayer->costo;

            if ($mainCosto === 0) {
                $rosaPlayer->delete();
                FantaListone::where('external_id', $externalId)->update(['stato' => 0]);
                return;
            }

            $teamKey = $this->normalizeRosaTeam($rosaPlayer->squadra);
            $goalkeeperSlotIndices = array_column(config('fantacalcio.rosa_goalkeeper_slots', []), 'index');

            $covers = FantaRosa::whereIn('slot_index', $goalkeeperSlotIndices)
                ->where('id', '!=', $rosaPlayer->id)
                ->lockForUpdate()
                ->get()
                ->filter(fn($r) => $this->normalizeRosaTeam($r->squadra) === $teamKey)
                ->sortBy('id')
                ->values();

            if ($covers->isNotEmpty()) {
                FantaRosa::where('id', $covers->first()->id)->update([
                    'costo' => $mainCosto,
                    'target_snapshot' => null,
                    'massimo_snapshot' => null,
                ]);
            }

            $rosaPlayer->delete();
            FantaListone::where('external_id', $externalId)->update(['stato' => 0]);
        });
    } catch (\Throwable $e) {
        return back()->with('error', 'Errore durante la rimozione: ' . $e->getMessage());
    }

    if ($errorMessage !== null) {
        return back()->with('error', $errorMessage);
    }

    return back()->with('success', 'Giocatore rimosso dalla rosa.');
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


// == CALCOLO AUTOMATICO LIVELLI (Formula C, percentili per ruolo) ==
public function updateLevels(Request $request)
{
    try {
        $this->recalculateLevels();
    } catch (\Throwable $e) {
        return back()->with('error', 'Errore aggiornando livelli/crediti: ' . $e->getMessage());
    }

    return back()->with('success', 'Livelli e crediti ricalcolati con successo.');
}

// Logica core del ricalcolo livelli — usata da updateLevels() e scoreRecalculate().
private function recalculateLevels(): void
{
    // Soglie: L5=max(P97,72)  L4=max(P87,65)  L3=max(P75,55)  L2=max(P50,45)
    $percDef  = [5 => 97.0, 4 => 87.0, 3 => 75.0, 2 => 50.0];
    $floorDef = [5 => 72.0, 4 => 65.0, 3 => 55.0, 2 => 45.0];

    $roleBudget = ['P' => 120, 'D' => 300, 'C' => 900, 'A' => 1180];
    $levelPerc  = [5 => 0.50, 4 => 0.15, 3 => 0.05, 2 => 0.01, 1 => 0.00];

    $rows = FantaListone::query()->select(['id', 'ruolo', 'score'])->get();

    if ($rows->isEmpty()) return;

    $pctValue = function (array $sorted, float $p) {
        $n = count($sorted);
        if ($n === 0) return 0.0;
        $i  = ($p / 100.0) * ($n - 1);
        $lo = (int) floor($i);
        $hi = (int) ceil($i);
        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($i - $lo);
    };

    $byRole     = $rows->groupBy('ruolo');
    $levelCase  = "CASE id ";
    $creditCase = "CASE id ";
    $ids        = [];

    foreach ($byRole as $role => $items) {
        $sorted = $items
            ->filter(fn($r) => $r->score !== null)
            ->pluck('score')
            ->map(fn($v) => (float) $v)
            ->sort()
            ->values()
            ->toArray();

        $thresh = [];
        foreach ([5, 4, 3, 2] as $lvl) {
            $thresh[$lvl] = max($pctValue($sorted, $percDef[$lvl]), $floorDef[$lvl]);
        }

        foreach ($items as $r) {
            if ($r->score === null) {
                $lvl = 1;
            } else {
                $s = (float) $r->score;
                if      ($s >= $thresh[5]) $lvl = 5;
                elseif  ($s >= $thresh[4]) $lvl = 4;
                elseif  ($s >= $thresh[3]) $lvl = 3;
                elseif  ($s >= $thresh[2]) $lvl = 2;
                else                       $lvl = 1;
            }

            $ids[]      = (int) $r->id;
            $levelCase .= "WHEN {$r->id} THEN {$lvl} ";

            $budget = $roleBudget[$role] ?? 0;
            if ($lvl === 1) {
                $credits = 1;
            } else {
                $credits = ($budget > 0) ? (int) floor($budget * ($levelPerc[$lvl] ?? 0.0)) : 0;
                $credits = max(1, $credits);
            }
            $creditCase .= "WHEN {$r->id} THEN " . min(2500, $credits) . " ";
        }
    }

    if (empty($ids)) return;

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
        throw $e;
    }
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

private function buildRosaSlots(): array
{
    $goalkeeperSlots = array_map(function (array $slot) {
        return array_merge($slot, [
            'role_token' => 'P',
            'title' => 'Portiere ' . ((int) $slot['index'] + 1),
            'level' => $slot['index'] === 0 ? 'Top' : 'Low',
            'hint' => 'Slot tecnico portieri, predisposto per futuri blocchi/treni.',
        ]);
    }, config('fantacalcio.rosa_goalkeeper_slots', []));

    $dcaSlots = [];
    foreach (config('fantacalcio.rosa_dca_slots', []) as $roleSlots) {
        foreach ($roleSlots as $slot) {
            $dcaSlots[] = array_merge($slot, [
                'role_token' => $slot['role'],
                'title' => $slot['label'],
            ]);
        }
    }

    return array_merge($goalkeeperSlots, $dcaSlots);
}

private function loadAssignedByIndex(): array
{
    $assignedRows = FantaRosa::orderBy('slot_index')->get([
        'slot_index', 'external_id', 'nome', 'squadra', 'costo', 'ruolo_esteso', 'classic_role', 'target_snapshot', 'massimo_snapshot'
    ]);
    $assignedByIndex = [];

    foreach ($assignedRows as $r) {
        $assignedByIndex[(int) $r->slot_index] = [
            'ext_id' => $r->external_id,
            'nome' => $r->nome,
            'team' => $r->squadra,
            'roles' => $r->ruolo_esteso,
            'classic_role' => $r->classic_role,
            'costo' => (int) $r->costo,
            'target_snapshot' => $r->target_snapshot,
            'massimo_snapshot' => $r->massimo_snapshot,
        ];
    }

    return $assignedByIndex;
}

    // --- IMPORT XLSX statistiche storiche ---
    public function statsImport(Request $request)
    {
        $request->validate([
            'xlsx'   => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'season' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $path   = $request->file('xlsx')->getRealPath();
        $season = $request->input('season');

        $colMap = [
            'id'  => 'external_id',
            'pv'  => 'pv',
            'mv'  => 'mv',
            'fm'  => 'fm',
            'gf'  => 'gf',
            'gs'  => 'gs',
            'rp'  => 'rp',
            'rc'  => 'rc',
            'r+'  => 'rplus',
            'r-'  => 'rminus',
            'ass' => 'ass',
            'amm' => 'amm',
            'esp' => 'esp',
            'au'  => 'au',
        ];

        $reader  = \OpenSpout\Reader\Common\Creator\ReaderFactory::createFromType('xlsx');
        $reader->open($path);

        $rows    = [];
        $idx     = [];
        $rowNum  = 0;
        $toFloat = fn($v) => is_float($v) || is_int($v)
            ? (float) $v
            : (float) str_replace(',', '.', (string) $v);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowNum++;
                $cells = [];
                foreach ($row->getCells() as $cell) {
                    $v = $cell->getValue();
                    $cells[] = is_string($v) ? trim($v) : $v;
                }

                if ($rowNum === 1) continue; // riga titolo

                if ($rowNum === 2) {
                    foreach ($cells as $pos => $label) {
                        $key = strtolower(trim((string) $label));
                        if (isset($colMap[$key])) {
                            $idx[$colMap[$key]] = $pos;
                        }
                    }
                    if (!isset($idx['external_id'])) {
                        $reader->close();
                        return back()->with('error', "Colonna 'Id' non trovata nel file statistiche.");
                    }
                    continue;
                }

                $extId = (int) $toFloat($cells[$idx['external_id']] ?? 0);
                if ($extId <= 0) continue;

                $r = ['external_id' => $extId, 'season' => $season];
                foreach (['pv', 'gf', 'gs', 'rp', 'rc', 'rplus', 'rminus', 'ass', 'amm', 'esp', 'au'] as $col) {
                    $r[$col] = isset($idx[$col]) ? (int) $toFloat($cells[$idx[$col]] ?? 0) : null;
                }
                foreach (['mv', 'fm'] as $col) {
                    $r[$col] = isset($idx[$col]) ? $toFloat($cells[$idx[$col]] ?? 0) : null;
                }
                $r['created_at'] = now();
                $r['updated_at'] = now();
                $rows[] = $r;
            }
            break;
        }
        $reader->close();

        if (empty($rows)) {
            return back()->with('error', 'Nessun dato trovato nel file statistiche.');
        }

        DB::beginTransaction();
        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                FantaPlayerStats::upsert(
                    $chunk,
                    ['external_id', 'season'],
                    ['pv', 'mv', 'fm', 'gf', 'gs', 'rp', 'rc', 'rplus', 'rminus', 'ass', 'amm', 'esp', 'au', 'updated_at']
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore import statistiche: ' . $e->getMessage());
        }

        return back()->with('success', "Statistiche importate: " . count($rows) . " giocatori (stagione {$season}).");
    }

    // --- RICALCOLO SCORE appetibilità (Formula C) ---
    public function scoreRecalculate(Request $request)
    {
        $season = $request->input('season') ?: null;

        try {
            $calculator = new AppetibilityCalculator();
            $count = $calculator->recalculate($season);
            $this->recalculateLevels();
        } catch (\Throwable $e) {
            return back()->with('error', 'Errore ricalcolo score: ' . $e->getMessage());
        }

        return back()->with('success', "Score aggiornato per {$count} giocatori e livelli ricalcolati.");
    }

    // --- IMPORT JSON fantagoat (fanta_index + titolarita) ---
    public function goatImport(Request $request)
    {
        $request->validate(['json' => ['required', 'file', 'mimes:json', 'max:20480']]);

        $content = file_get_contents($request->file('json')->getRealPath());
        $items   = json_decode($content, true)['items'] ?? [];

        $codeMap = [
            'ATA' => 'Atalanta',  'BOL' => 'Bologna',   'CAG' => 'Cagliari',
            'COM' => 'Como',      'FIO' => 'Fiorentina', 'FRO' => 'Frosinone',
            'GEN' => 'Genoa',     'INT' => 'Inter',      'JUV' => 'Juventus',
            'LAZ' => 'Lazio',     'LEC' => 'Lecce',      'MIL' => 'Milan',
            'MON' => 'Monza',     'NAP' => 'Napoli',     'PAR' => 'Parma',
            'ROM' => 'Roma',      'SAS' => 'Sassuolo',   'TOR' => 'Torino',
            'UDI' => 'Udinese',   'VEN' => 'Venezia',
        ];

        $updated  = 0;
        $transferred = 0;
        $notFound = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $squadra = $codeMap[$item['team_short_code']] ?? null;
                if (!$squadra) { $notFound++; continue; }

                $titolare = (int) round(((int)$item['titolarita'] + (int)$item['continuita']) / 2);
                $fi       = (int) $item['fanta_index'];

                // Match esatto nome + squadra
                $rows = FantaListone::where('nome', $item['display_name'])
                    ->where('squadra', $squadra)
                    ->get();

                if ($rows->isNotEmpty()) {
                    foreach ($rows as $player) {
                        $player->fanta_index = $fi;
                        $player->titolare    = $titolare;
                        $player->save();
                        $updated++;
                    }
                    continue;
                }

                // Fallback: match per solo nome (giocatore trasferito)
                $byName = FantaListone::where('nome', $item['display_name'])->get();
                if ($byName->count() === 1) {
                    $byName->first()->update(['fanta_index' => $fi, 'titolare' => $titolare]);
                    $transferred++;
                } else {
                    $notFound++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore import fantagoat: ' . $e->getMessage());
        }

        return back()->with('success', "Fantagoat import: aggiornati {$updated}, trasferiti {$transferred}, non trovati {$notFound}.");
    }

    // --- IMPORT XLSX esperto2 (titolarita media + fanta_fascia) ---
    public function esperto2Import(Request $request)
    {
        $request->validate(['xlsx' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480']]);

        $path   = $request->file('xlsx')->getRealPath();
        $reader = \OpenSpout\Reader\Common\Creator\ReaderFactory::createFromType('xlsx');
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        $data = array_slice($rows, 1);

        $listone = FantaListone::select(['id', 'external_id', 'titolare'])
            ->get()
            ->keyBy('external_id');

        $updated  = 0;
        $skipped  = 0;
        $notFound = 0;

        DB::beginTransaction();
        try {
            foreach ($data as $row) {
                $extId   = isset($row[0]) && $row[0] !== '' ? (int) $row[0] : null;
                $titRaw  = isset($row[5]) && $row[5] !== '' ? (int) $row[5] : null;
                $fasciaRaw = isset($row[4]) && $row[4] !== '' ? (int) $row[4] : null;

                if ($extId === null || $titRaw === null) { $skipped++; continue; }
                if ($titRaw < 1 || $titRaw > 5)         { $skipped++; continue; }
                if (!$listone->has($extId))              { $notFound++; continue; }

                $player  = $listone->get($extId);
                $titNorm = $titRaw * 20;
                $existing = $player->titolare !== null ? (int) $player->titolare : null;
                $newTit  = $existing !== null ? (int) round(($existing + $titNorm) / 2) : $titNorm;

                FantaListone::where('id', $player->id)->update([
                    'titolare'     => $newTit,
                    'fanta_fascia' => ($fasciaRaw >= 1 && $fasciaRaw <= 8) ? $fasciaRaw : null,
                    'updated_at'   => now(),
                ]);
                $updated++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Errore import esperto2: ' . $e->getMessage());
        }

        return back()->with('success', "Esperto2 import: aggiornati {$updated}, senza valutazione {$skipped}, non trovati {$notFound}.");
    }


}


