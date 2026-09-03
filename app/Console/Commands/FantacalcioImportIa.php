<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\FantaListone;

class FantacalcioImportIa extends Command
{
    protected $signature = 'fantacalcio:import-ia {file : Percorso del file JSON con l\'Indice di Appetibilità}';
    protected $description = 'Importa l\'Indice di Appetibilità (IA) da un file JSON nel listone fantacalcio';

    public function handle()
    {
        $path = $this->argument('file');

        if (!file_exists($path)) {
            $this->error("File non trovato: {$path}");
            return 1;
        }

        $content = file_get_contents($path);
        $json    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($json['players']) || !is_array($json['players'])) {
            $this->error('JSON non valido o struttura inattesa (campo "players" mancante o non array).');
            return 1;
        }

        $players = $json['players'];
        $total   = count($players);

        $this->info("File: {$path}");
        $this->info("Giocatori nel file: {$total}");
        $this->newLine();

        // Precarica il listone in memoria
        $listone = FantaListone::select(['id', 'nome', 'ruolo'])->get();

        // --- Indice primario: normalize(nome)|ruolo → lista di id ---
        $primaryIndex = [];

        // --- Indice secondario: normalize(cognome)|ruolo → lista di [id, initial, nome] ---
        // Formato DB atteso: "Cognome N." oppure "Cognome" (mononym)
        // cognome = prima parola, initial = seconda parola (≤2 char dopo strip dot)
        $secondaryIndex = [];

        foreach ($listone as $row) {
            $normalized = $this->normalize($row->nome);

            // Primario
            $primaryIndex[$normalized . '|' . $row->ruolo][] = $row->id;

            // Secondario
            $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($parts)) continue;

            $cognome = $parts[0];
            $initial = '';

            if (isset($parts[1])) {
                $secondPart = rtrim($parts[1], '.');
                // Se il secondo token è troppo lungo non è un'iniziale (es. "De Ketelaere")
                if (strlen($secondPart) > 2) continue;
                $initial = $secondPart;
            }

            $secondaryIndex[$cognome . '|' . $row->ruolo][] = [
                'id'      => $row->id,
                'initial' => $initial,
                'nome'    => $row->nome,
            ];
        }

        $primaryUpdated     = 0;
        $secondaryUpdated   = 0;
        $notFound           = [];
        $ambiguous          = [];
        $secondaryAmbiguous = [];
        $errors             = 0;
        $updateRows         = [];

        foreach ($players as $entry) {
            $nome  = isset($entry['nome'])  ? (string) $entry['nome']  : '';
            $ruolo = isset($entry['ruolo']) ? (string) $entry['ruolo'] : '';
            $ia    = isset($entry['ia'])    ? $entry['ia']             : null;

            if ($nome === '' || $ruolo === '' || $ia === null) {
                $errors++;
                continue;
            }

            $ia       = (int) $ia;
            $normNome = $this->normalize($nome);

            // === Matching primario: nome esatto normalizzato + ruolo ===
            $primaryKey = $normNome . '|' . $ruolo;

            if (isset($primaryIndex[$primaryKey])) {
                $ids = $primaryIndex[$primaryKey];

                if (count($ids) > 1) {
                    $ambiguous[] = "{$nome} ({$ruolo}) — " . count($ids) . " record nel DB";
                    continue;
                }

                $updateRows[$ids[0]] = $this->buildRow($ia, $entry);
                $primaryUpdated++;
                continue;
            }

            // === Matching secondario: cognome (ultima parola JSON) + iniziale ===
            $normParts   = preg_split('/\s+/', $normNome, -1, PREG_SPLIT_NO_EMPTY);
            $cognomeJson = end($normParts);
            // Prima lettera della prima parola; null se il JSON è già mononymico
            $initialJson = count($normParts) > 1 ? substr($normParts[0], 0, 1) : null;

            $secKey = $cognomeJson . '|' . $ruolo;

            if (!isset($secondaryIndex[$secKey])) {
                $notFound[] = "{$nome} ({$ruolo})";
                continue;
            }

            $candidates = $secondaryIndex[$secKey];

            if ($initialJson === null) {
                // JSON mononym → accetta qualsiasi candidato con quel cognome+ruolo
                $matches = $candidates;
            } else {
                // Multi-parola: l'iniziale DB deve essere vuota (mononym) oppure iniziare con initialJson
                $matches = array_values(array_filter($candidates, function ($c) use ($initialJson) {
                    return $c['initial'] === '' || strpos($c['initial'], $initialJson) === 0;
                }));
            }

            if (count($matches) === 1) {
                $secondaryUpdated++;
                $updateRows[$matches[0]['id']] = $this->buildRow($ia, $entry);
            } elseif (count($matches) === 0) {
                $notFound[] = "{$nome} ({$ruolo})";
            } else {
                $candidateNames = implode(', ', array_column($matches, 'nome'));
                $secondaryAmbiguous[] = "{$nome} ({$ruolo}) → candidati: {$candidateNames}";
            }
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

        $totalUpdated = $primaryUpdated + $secondaryUpdated;
        $nf = count($notFound);
        $am = count($ambiguous) + count($secondaryAmbiguous);

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Giocatori letti:            {$total}");
        $this->info("Aggiornati (primario):      {$primaryUpdated}");
        $this->info("Aggiornati (secondario):    {$secondaryUpdated}");
        $this->info("Aggiornati totale:          {$totalUpdated}");
        $this->line($nf > 0 ? "<fg=yellow>Non trovati:                {$nf}</>" : "Non trovati:                0");
        $this->line($am > 0 ? "<fg=yellow>Ambigui:                    {$am}</>" : "Ambigui:                    0");
        $this->line($errors > 0 ? "<fg=red>Errori (ia null nel file):  {$errors}</>" : "Errori (ia null nel file):  0");
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($notFound)) {
            $this->newLine();
            $this->warn('Giocatori non trovati nel DB:');
            foreach ($notFound as $name) {
                $this->line("  - {$name}");
            }
        }

        if (!empty($ambiguous)) {
            $this->newLine();
            $this->warn('Ambigui primario (stesso nome+ruolo, più record DB):');
            foreach ($ambiguous as $name) {
                $this->line("  - {$name}");
            }
        }

        if (!empty($secondaryAmbiguous)) {
            $this->newLine();
            $this->warn('Ambigui secondario (più candidati per cognome+iniziale):');
            foreach ($secondaryAmbiguous as $name) {
                $this->line("  - {$name}");
            }
        }

        return 0;
    }

    private function buildRow(int $ia, array $entry): array
    {
        return [
            'ia'            => $ia,
            'ia_confidence' => isset($entry['confidence'])        ? (int)    $entry['confidence']        : null,
            'ia_status'     => isset($entry['stato_valutazione']) ? (string) $entry['stato_valutazione'] : null,
            'ia_sources'    => isset($entry['fonti_conteggio'])   ? (int)    $entry['fonti_conteggio']   : null,
            'updated_at'    => now(),
        ];
    }

    private function normalize(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        return mb_strtolower(Str::ascii($name), 'UTF-8');
    }
}
