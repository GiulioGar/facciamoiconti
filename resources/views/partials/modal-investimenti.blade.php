{{-- resources/views/partials/modal-investimenti.blade.php --}}
<div class="modal fade" id="modal-investimenti" tabindex="-1" aria-labelledby="modalInvestimentiLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('investments.store') }}">
      @csrf
      <input type="hidden" name="family_id" value="{{ $family->id }}">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalInvestimentiLabel">Modifica Investimenti</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            @foreach($investmentCategories as $idx => $cat)
              <div class="col-md-4">
                <label class="form-label">{{ $cat->name }}</label>

                <div class="input-group mb-2">
                  <span class="input-group-text">Saldo</span>
                  <input
                    type="number"
                    name="investments[{{ $idx }}][current_balance]"
                    class="form-control"
                    step="0.01"
                    value="{{ old('investments.'.$idx.'.current_balance', $latestInvestments[$cat->id]['current_balance'] ?? 0) }}"
                    required
                  >
                </div>

                <div class="input-group mb-2">
                  <span class="input-group-text">Investito</span>
                  <input
                    type="number"
                    name="investments[{{ $idx }}][invested_balance]"
                    class="form-control"
                    step="0.01"
                    value="{{ old('investments.'.$idx.'.invested_balance', $latestInvestments[$cat->id]['invested_balance'] ?? 0) }}"
                    required
                  >
                </div>

                <input
                  type="hidden"
                  name="investments[{{ $idx }}][category_id]"
                  value="{{ $cat->id }}"
                >
              </div>
            @endforeach

            <div class="col-12">
              <label for="accounting_month_inv" class="form-label">Mese contabile</label>
              <select name="accounting_month" id="accounting_month_inv" class="form-select" required>
                @foreach($balanceMonths as $m)
                  <option value="{{ $m }}" {{ $m === $period ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('F Y') }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">Salva Modifiche</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        </div>
      </div>
    </form>
  </div>
</div>
