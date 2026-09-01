# Project Memory

Last update: 2026-09-01

## Stack and constraints

- Project: `facciamoiconti`
- Framework: Laravel `8`
- PHP target: `7.4`
- Environment: local XAMPP, deploy on GoogieHost
- Frontend: Blade + Sneat Bootstrap 5 free
- Do not upgrade Laravel or PHP
- Keep changes small and reviewable
- Avoid new packages unless explicitly requested
- Do not change routes, DB schema, or public method names unless explicitly requested

## Current active area

- Active work is on `/fantacalcio`
- Older `AGENTS.md` priority about `PanelUsersController` is not the current focus for this thread
- Main files currently relevant:
  - `app/Http/Controllers/FantacalcioController.php`
  - `app/Services/Fantacalcio/RosaBudgetCalculator.php`
  - `config/fantacalcio.php`
  - `resources/views/fantacalcio/rosa.blade.php`
  - `tests/Unit/RosaBudgetCalculatorTest.php`
  - `tests/Feature/FantacalcioRosaGoalkeeperBlockTest.php`

## Fantacalcio strategy baseline

- Reparti confermati:
  - `P = 5%`
  - `D = 9%`
  - `C = 30%`
  - `A = 56%`

- Portieri:
  - `6` slot tecnici `P1..P6`
  - modello economico a blocchi per squadra normalizzata
  - il primo portiere del blocco porta il costo
  - le coperture dello stesso blocco hanno costo `0`
  - `target_blocks = 2`
  - nessun `TARGET/MASSIMO` per singolo slot portiere

- Difesa pesi:
  - `D = 6, 4, 4, 4, 2, 2, 1, 1`

- Centrocampo pesi:
  - `C = 8, 6, 5, 3, 2, 1, 1, 1`

- Attacco pesi:
  - `A = 12, 9, 4, 2, 1, 1`

## Planner current behavior

- `TARGET` = valore consigliato corrente dello slot aperto
- `MASSIMO` = warning strategico, non blocca acquisti
- Il backend blocca solo quando i crediti reali non bastano
- `RosaBudgetCalculator` ora restituisce per gli slot `D/C/A` aperti:
  - `suggested`
  - `target`
  - `massimo`
  - `hard_cap`
  - `max_extra_percentage`

## MASSIMO formula currently implemented

For each open `D/C/A` slot:

- `T_i = target`
- `m_i = min_cost`
- `R = remaining`
- `F_other(i) = completion_floor totale - min_cost dello slot`
- `H_i = max(m_i, R - F_other(i))`
- `remaining_target_reparto = max(0, target_reparto - speso_reparto)`
- `g_raw = clamp(remaining_target_reparto / target_reparto, 0, 1)`
- `g_soft = 0.5 + (0.5 * g_raw)`
- `a_i = clamp((H_i - T_i) / max(1, T_i), 0, 1)`
- `MASSIMO_i = min(H_i, T_i + ceil(T_i * max_extra_percentage * g_soft * a_i))`

Configured `max_extra_percentage`:

- Difesa:
  - `D1 0.30`
  - `D2 0.25`
  - `D3 0.20`
  - `D4 0.20`
  - `D5 0.15`
  - `D6 0.15`
  - `D7 0.05`
  - `D8 0.05`

- Centrocampo:
  - `C1 0.30`
  - `C2 0.25`
  - `C3 0.20`
  - `C4 0.15`
  - `C5 0.10`
  - `C6 0.05`
  - `C7 0.05`
  - `C8 0.05`

- Attacco:
  - `A1 0.25`
  - `A2 0.20`
  - `A3 0.15`
  - `A4 0.10`
  - `A5 0.05`
  - `A6 0.05`

## Reference values with budget 500

- Centrocampo:
  - `C1 43 / 56`
  - `C2 33 / 42`
  - `C3 27 / 33`
  - `C4 17 / 20`
  - `C5 12 / 14`
  - `C6 6 / 7`
  - `C7 6 / 7`
  - `C8 6 / 7`

- Attacco:
  - `A1 114 / 143`
  - `A2 86 / 104`
  - `A3 39 / 45`
  - `A4 20 / 22`
  - `A5 11 / 12`
  - `A6 10 / 11`

## Snapshot persistence now in place

- `fanta_rosa` now stores:
  - `target_snapshot`
  - `massimo_snapshot`

- Snapshot rule:
  - for `D/C/A`, save planner `target` and `massimo` from the pre-purchase state
  - for `P`, save both as `null`

- Important:
  - do not reconstruct occupied-slot target/max from current planner
  - use only saved snapshots for historic comparison

## Current rosa UI state

- Top summary shows:
  - `Budget totale`
  - `Speso`
  - `Residuo`
  - `Fondo minimo`
  - `Budget strategico`

- Reparto summary shows:
  - `Target reparto`
  - `Speso reparto`
  - `Residuo target`

- Open `D/C/A` slots show:
  - `slot_code`
  - label
  - current `Target`
  - current `Massimo`
  - button `Seleziona`

- Occupied `D/C/A` slots show:
  - player
  - team
  - real cost
  - snapshot comparison line if available:
    - `Pagato X · Target Y · Max Z · delta`
  - visual state:
    - `success` if `costo <= target_snapshot`
    - `warning` if `costo > target_snapshot && costo <= massimo_snapshot`
    - `danger` if `costo > massimo_snapshot`
  - if snapshots are missing:
    - show `Riferimento strategico non disponibile`
    - do not invent values

- Portieri show:
  - `slot_code`
  - player
  - team
  - cost
  - `Blocco` if cost `> 0`
  - `Copertura` if cost `= 0`

## Operational notes for future edits

- Prefer changing only one layer at a time:
  - config
  - calculator
  - persistence
  - UI
  - tests

- For any change to slot strategy:
  - update `config/fantacalcio.php`
  - verify `RosaBudgetCalculatorTest`
  - verify `FantacalcioRosaGoalkeeperBlockTest`

- For occupied-slot UI:
  - use `target_snapshot` and `massimo_snapshot`
  - never use current planner values as historical reference

- For portieri:
  - keep block logic separate from `D/C/A`
  - avoid forcing `TARGET/MASSIMO` semantics onto single goalkeeper slots unless the model changes explicitly

## Environment limits observed in this workspace

- `php artisan test` is not available in this local setup
- direct PHPUnit execution was also not readily available from the current vendor/bin setup
- `php -l` works and has been used for syntax checks
- some deeper runtime checks may fail locally if optional PHP drivers are missing

## Current expectation before future code changes

If a task asks for analysis first, follow this sequence:

1. explain the problem found
2. indicate files and methods involved
3. propose a minimal fix
4. wait for approval before applying changes

This is especially important because `AGENTS.md` explicitly asks for approval-first workflow before code changes.
