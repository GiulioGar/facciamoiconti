@extends('layouts.app')

@section('title', 'Entrate')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Button per aprire modal --}}
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNewIncome">
      + Nuova Entrata
    </button>
  </div>

  {{-- Tabella entrate --}}
  <div class="card">
    <div class="card-header">Elenco Entrate</div>
    <div class="card-body table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Entrata</th>
            <th>Importo</th>
            <th>Mese</th>
            <th>Budget</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          @foreach($incomes as $income)
            @foreach($income->allocations as $alloc)
              <tr>
                <td>{{ $income->description ?? '–' }}</td>
                <td>€ {{ number_format($alloc->amount, 2, ',', '.') }}</td>
                <td>
                {{ \Carbon\Carbon::parse($income->date)
                        ->isoFormat('MMMM YYYY') }}
                </td>
                <td>
                  {{-- se c’è category: nome, altrimenti il tipo --}}
                  {{ $alloc->category
                       ? $alloc->category->name
                       : ucfirst($alloc->type) }}
                </td>
                <td>—</td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>

      {{-- Paginazione --}}
      {{ $incomes->links() }}
    </div>
  </div>
</div>

{{-- Modal Nuova Entrata (come definito precedentemente) --}}
<div class="modal fade" id="modalNewIncome" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">

    <div id="allocation-alert" class="alert alert-warning d-none">
        Suddividere l'intero budget
        </div>

    <form method="POST" action="{{ route('incomes.store') }}">
      @csrf
      <input type="hidden" name="family_id" value="{{ $family->id }}">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nuova Entrata</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          {{-- Importo --}}
            <div class="mb-3">
            <label class="form-label">Importo (€)</label>
            <input
                type="number" step="0.01" name="amount" id="amount"
                class="form-control @error('amount') is-invalid @enderror"
                value="{{ old('amount') }}" required>
            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

        <!-- Date -->
        <div class="mb-3">
        <label class="form-label">Data</label>
        <input
            type="date" name="date" id="date"
            class="form-control @error('date') is-invalid @enderror"
            value="{{ old('date', today()->toDateString()) }}" required>
        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

          {{-- Descrizione --}}
          <div class="mb-3">
            <label class="form-label">Descrizione</label>
            <input type="text" name="description"
                   class="form-control @error('description') is-invalid @enderror"
                   value="{{ old('description') }}">
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

<!-- Ripartizioni -->
<div class="card mb-0">
  <div class="card-header">Ripartizione Budget</div>
  <div class="card-body">
    @php
      // Percentuali di default
      $percentages = [
        'personale' => 15,
        'familiare' => 55,
        'extra'     => 15,
        'risparmi'  => 15,
      ];
    @endphp

            @foreach($categories as $cat)
        <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">{{ $cat->name }}</label>
            <div class="col-sm-8">
            <input
                type="number" step="0.01"
                name="allocations[{{ $cat->id }}]"            {{-- usa ID --}}
                id="budget-{{ $cat->slug }}"
                class="form-control budget-input"
                data-percentage="{{ $percentages[$cat->slug] }}"
                value="{{ old('allocations.'.$cat->id, 0) }}" required>
            </div>
        </div>
        @endforeach

        </div>


    <!-- alert sotto i budget -->
    <div id="allocation-alert" class="alert alert-warning mt-2 d-none">
      Suddividere l'intero budget
    </div>

        <div class="modal-footer">
            <button type="submit" id="save-income-btn" class="btn btn-primary" disabled> Salva </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Annulla
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
  console.log("Inizializzazione script Entrate");

  // Funzione che applica le % di default
  function applyDefaultAllocations() {
    let total = parseFloat($('#amount').val()) || 0;
    $('.budget-input').each(function(){
      let perc = parseFloat($(this).data('percentage')) || 0;
      let val  = (total * perc/100).toFixed(2);
      $(this).val(val);
    });
  }

  // Funzione che controlla somma vs totale
  function validateSum() {
    let total = parseFloat($('#amount').val()) || 0;
    let sum   = 0;
    $('.budget-input').each(function(){
      sum += parseFloat($(this).val()) || 0;
    });
    if (Math.abs(sum - total) > 0.009) {
      $('#allocation-alert').removeClass('d-none');
      $('#save-income-btn').prop('disabled', true);
    } else {
      $('#allocation-alert').addClass('d-none');
      $('#save-income-btn').prop('disabled', false);
    }
  }

  // Al change di Importo: ricalcola e valida
  $(document).on('input', '#amount', function(){
    applyDefaultAllocations();
    validateSum();
  });

  // Ogni modifica manuale di un budget: valida
  $(document).on('input', '.budget-input', validateSum);

  // Applica default e valida al momento dell'apertura del modal
  $('#modalNewIncome').on('shown.bs.modal', function(){
    applyDefaultAllocations();
    validateSum();
  });
});
</script>
@endpush


