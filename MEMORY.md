# Memory

## Fantacalcio base strategy

Last update: 2026-09-01

- Reparti confermati: `P=5%`, `D=9%`, `C=30%`, `A=56%`.
- Modello portieri invariato: `6` slot tecnici, blocchi economici per squadra normalizzata, primo blocco valorizzato economicamente, cover a costo `0`, `target_blocks=2`.
- Difesa confermata: `D = 6, 4, 4, 4, 2, 2, 1, 1`.
- Centrocampo confermato: `C = 8, 6, 5, 3, 2, 1, 1, 1`.
- Attacco confermato: `A = 12, 9, 4, 2, 1, 1`.

## Planner reference values with budget 500

- `C1..C8 = 43, 33, 27, 17, 12, 6, 6, 6`.
- `A1..A6 = 114, 86, 39, 20, 11, 10`.

## Notes

- Non modificare `RosaBudgetCalculator` per questa strategia base.
- Sono presenti modifiche locali non ancora integrate in `FantacalcioController.php` e `RosaBudgetCalculator.php`: non includerle automaticamente nei commit della configurazione base.
