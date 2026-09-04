<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FantaListone;

class FantacalcioImportEsperto2 extends Command
{
    protected $signature = 'fantacalcio:import-esperto2 {file : Percorso del file XLSX esperto2}';
    protected $description = 'Importa titolarità da esperto2 (media con valore esistente) nel listone fantacalcio';

    // Normalizza titolarità 1-5 → 0-100
    // 1→20, 2→40, 3→60, 4→80, 5→100
    private function normalizeTitolarita(int $val): int
    {
        return max(0, min(100, $val * 20));
    }

    public function handle()
    {
        $path = $this->argument('file');

        if (!file_exists($path)) {
            $this->error("File non trovato: {$path}");
            return 1;
        }

        $reader = \OpenSpout\Reader\Common\Creator\ReaderFactory::createFromType('xlsx');
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break; // solo primo foglio
        }
        $reader->close();

        if (empty($rows)) {
            $this->error('File vuoto.');
            return 1;
        }

        // Salta header
        $data = array_slice($rows, 1);

        // Precarica listone indicizzato per external_id
        $listone = FantaListone::select(['id', 'external_id', 'titolare'])
            ->get()
            ->keyBy('external_id');

        $updated  = 0;
        $skipped  = 0;
        $notFound = [];
        $updateRows = [];

        foreach ($data as $row) {
            $extId   = isset($row[0]) && $row[0] !== '' ? (int) $row[0] : null;
            $titRaw  = isset($row[5]) && $row[5] !== '' ? (int) $row[5] : null;

            // Riga senza Id o senza Titolarità → non valutata dall'esperto
            if ($extId === null || $titRaw === null) {
                $skipped++;
                continue;
            }

            if ($titRaw < 1 || $titRaw > 5) {
                $skipped++;
                continue;
            }

            if (!$listone->has($extId)) {
                $notFound[] = $extId . ' - ' . ($row[2] ?? '?') . ' (' . ($row[1] ?? '?') . ')';
                continue;
            }

            $player   = $listone->get($extId);
            $titNorm  = $this->normalizeTitolarita($titRaw);
            $existing = $player->titolare !== null ? (int) $player->titolare : null;
            $fasciaRaw = isset($row[4]) && $row[4] !== '' ? (int) $row[4] : null;

            $newTit = $existing !== null
                ? (int) round(($existing + $titNorm) / 2)
                : $titNorm;

            $updateRows[$player->id] = [
                'titolare'     => $newTit,
                'fanta_fascia' => ($fasciaRaw >= 1 && $fasciaRaw <= 8) ? $fasciaRaw : null,
                'updated_at'   => now(),
            ];
            $updated++;
        }

        if (!empty($updateRows)) {
            DB::beginTransaction();
            try {
                foreach ($updateRows as $id => $data) {
                    FantaListone::where('id', $id)->update($data);
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Errore durante il salvataggio: ' . $e->getMessage());
                return 1;
            }
        }

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("Aggiornati:        {$updated}");
        $this->line("Senza valutazione: {$skipped}");
        $nf = count($notFound);
        $this->line($nf > 0 ? "<fg=yellow>Non trovati in DB: {$nf}</>" : "Non trovati in DB: 0");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($notFound)) {
            $this->newLine();
            $this->warn('Id non presenti nel listone:');
            foreach ($notFound as $name) {
                $this->line("  - {$name}");
            }
        }

        return 0;
    }
}
