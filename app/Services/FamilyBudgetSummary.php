<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FamilyBudgetSummary
{
    /**
     * Calcola i totali per famiglia aggregando:
     * - start_amount GLOBALI per nome categoria (conteggiati una sola volta)
     * - allocazioni (income_allocations) della famiglia
     * - uscite della famiglia
     *
     * Ritorna array:
     * [
     *   'familiare' => float,
     *   'extra'     => float,
     *   'risparmi'  => float,
     *   'personale' => float,
     *   'totale'    => float,
     *   'breakdown' => [
     *       '<catId>' => ['name' => ..., 'start' => ..., 'inc_all' => ..., 'exp_all' => ..., 'net' => ...],
     *   ],
     * ]
     */
    public static function build(int $userId, int $familyId): array
    {
        // --- Start amounts GLOBALI (per nome categoria, una sola volta)
        $startRows = DB::table('budget_categories')
            ->select('name', DB::raw('MAX(start_amount) as start_amount_one'))
            ->whereIn('name', ['Familiare', 'Extra', 'Risparmi', 'Personale'])
            ->groupBy('name')
            ->get();

        $start = [
            'Familiare' => 0.0,
            'Extra'     => 0.0,
            'Risparmi'  => 0.0,
            'Personale' => 0.0,
        ];
        foreach ($startRows as $r) {
            $start[$r->name] = (float) $r->start_amount_one;
        }

        // --- Totale entrate allocate per FAMIGLIA, raggruppate per NOME categoria
        $allocations = DB::table('income_allocations as ia')
            ->join('incomes as i', 'i.id', '=', 'ia.income_id')
            ->leftJoin('budget_categories as bc', 'bc.id', '=', 'ia.category_id')
            ->where('i.family_id', $familyId)
            ->select(
                DB::raw("COALESCE(bc.name, ia.type) as bucket"),
                DB::raw('SUM(ia.amount) as total')
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->toArray();

        // --- Totale uscite per FAMIGLIA, raggruppate per NOME categoria
        $expenses = DB::table('expenses as e')
            ->leftJoin('budget_categories as bc', 'bc.id', '=', 'e.budget_category_id')
            ->where('e.family_id', $familyId)
            ->select(
                DB::raw("COALESCE(bc.name, 'Senza Categoria') as bucket"),
                DB::raw('SUM(e.amount) as total')
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->toArray();

        // --- Macro categorie
        $familiare = $start['Familiare'] + (float)($allocations['Familiare'] ?? 0) - (float)($expenses['Familiare'] ?? 0);
        $extra     = $start['Extra']     + (float)($allocations['Extra'] ?? 0)     - (float)($expenses['Extra'] ?? 0);
        $risparmi  = $start['Risparmi']  + (float)($allocations['Risparmi'] ?? 0)  - (float)($expenses['Risparmi'] ?? 0);
        $personale = $start['Personale'] + (float)($allocations['Personale'] ?? 0) - (float)($expenses['Personale'] ?? 0);

        $totale = $familiare + $extra + $risparmi + $personale;

        return [
            'familiare' => $familiare,
            'extra'     => $extra,
            'risparmi'  => $risparmi,
            'personale' => $personale,
            'totale'    => $totale,
            // opzionale: breakdown dettagliato per futuri tooltip/espansioni
            'breakdown' => [
                'Familiare' => [
                    'name'    => 'Familiare',
                    'start'   => $start['Familiare'],
                    'inc_all' => (float)($allocations['Familiare'] ?? 0),
                    'exp_all' => (float)($expenses['Familiare'] ?? 0),
                    'net'     => $familiare,
                ],
                'Extra' => [
                    'name'    => 'Extra',
                    'start'   => $start['Extra'],
                    'inc_all' => (float)($allocations['Extra'] ?? 0),
                    'exp_all' => (float)($expenses['Extra'] ?? 0),
                    'net'     => $extra,
                ],
                'Risparmi' => [
                    'name'    => 'Risparmi',
                    'start'   => $start['Risparmi'],
                    'inc_all' => (float)($allocations['Risparmi'] ?? 0),
                    'exp_all' => (float)($expenses['Risparmi'] ?? 0),
                    'net'     => $risparmi,
                ],
                'Personale' => [
                    'name'    => 'Personale',
                    'start'   => $start['Personale'],
                    'inc_all' => (float)($allocations['Personale'] ?? 0),
                    'exp_all' => (float)($expenses['Personale'] ?? 0),
                    'net'     => $personale,
                ],
            ],
        ];
    }
}
