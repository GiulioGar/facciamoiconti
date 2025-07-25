<div class="modal fade" id="edit-{{ $field }}" tabindex="-1" aria-labelledby="edit-{{ $field }}-label" aria-hidden="true">
  <div class="modal-dialog">
<form method="POST" action="{{ route('balance.store') }}">
  @csrf
  <input type="hidden" name="family_id" value="{{ $family->id }}">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="edit-{{ $field }}-label">Modifica {{ $label }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">


          {{-- Campo valore --}}
          <div class="mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <input
              type="number"
              id="{{ $field }}"
              name="{{ $field }}"
              class="form-control"
              step="0.01"
              value="{{ old($field, $value) }}"
              required
            >
          </div>

                    {{-- Select mese contabile --}}
<div class="mb-3">
  <label for="accounting_month" class="form-label">Mese contabile</label>
  <select name="accounting_month" id="accounting_month" class="form-select" required>
    @foreach($months as $m)
      <option
        value="{{ $m }}"
        {{ $m === $period ? 'selected' : '' }}
      >
        {{ \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('F Y') }}
      </option>
    @endforeach
  </select>
</div>


        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Salva</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        </div>
      </div>
    </form>
  </div>
</div>
