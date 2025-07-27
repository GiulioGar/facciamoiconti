{{-- Modal unico per aggiornare tutti i campi --}}
<div class="modal fade" id="editBalanceModal" tabindex="-1"
     aria-labelledby="editBalanceLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('balance.store') }}">
      @csrf
      <input type="hidden" name="family_id" value="{{ $family->id }}">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editBalanceLabel">
            Aggiorna Suddivisione Attività
          </h5>
          <button type="button" class="btn-close"
                  data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            @foreach ([
              'bank_balance'   => 'Saldo in Banca',
              'other_accounts' => 'Altri Conti',
              'cash'           => 'Contanti',
              'insurances'     => 'Polizze',
              'investments'    => 'Investimenti',
              'debt_credit'    => 'Debito / Credito',
            ] as $field => $label)
              <div class="col-12">
                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                <input
                  type="number"
                  id="{{ $field }}"
                  name="{{ $field }}"
                  class="form-control"
                  step="0.01"
                  value="{{ old($field, $latestBalance ? $latestBalance->$field : 0) }}"
                  required
                >
              </div>
            @endforeach

            {{-- Selezione mese contabile --}}
            <div class="col-12">
              <label for="accounting_month" class="form-label">
                Mese contabile
              </label>
              <select name="accounting_month"
                      id="accounting_month"
                      class="form-select"
                      required>
                @foreach($balanceMonths as $m)
                  <option value="{{ $m }}"
                    {{ $m === $period ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $m)
                         ->translatedFormat('F Y') }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Salva</button>
          <button type="button" class="btn btn-secondary"
                  data-bs-dismiss="modal">Annulla</button>
        </div>
      </div>
    </form>
  </div>
</div>
