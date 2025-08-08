@extends('layouts.app')

@section('title', 'Entrate')

@section('navbar-title')
<span class="d-flex align-items-center gap-2">
  <i class="bi bi-box-arrow-in-right fs-5 text-primary"></i>
  <span class="fw-semibold">Le tue entrate</span>
</span>
@endsection

@section('content')

@php
  use Illuminate\Support\Str;

  // Totali per key "YYYY-MM|descrizione"
  $groupTotals = [];
  // Solo per il rendering: traccia quali gruppi hanno già mostrato il totale
  $groupShown  = [];

  foreach ($incomes as $inc) {
    $monthKey = \Carbon\Carbon::parse($inc->date)->format('Y-m');
    $descRaw  = trim((string)($inc->description ?? ''));
    if ($descRaw === '') continue; // niente gruppo per descrizioni vuote

    // normalizzazione chiave (case-insensitive, unicode safe)
    $descKey  = mb_strtolower($descRaw, 'UTF-8');
    $key      = $monthKey.'|'.$descKey;

    foreach ($inc->allocations as $alloc) {
      $groupTotals[$key] = ($groupTotals[$key] ?? 0) + (float)$alloc->amount;
    }
  }
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Pulsante Nuova Entrata --}}
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNewIncome">
      + Nuova Entrata
    </button>
  </div>

  {{-- VERSIONE TABELLA (desktop) --}}
  <div class="card d-none d-md-block">
    <div class="card-header">Elenco Entrate</div>
    <div class="card-body table-responsive pt-3">
      <table id="income-table" class="table table-modern table-hover w-100 dt-responsive nowrap">
        <thead style="background-color: #f8f9fa!important; color: #343a40!important;">
          <tr>
            <th class="force-show">Entrata</th>
            <th class="force-show">Importo</th>
            <th class="force-show">Mese</th>
            <th>Budget</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          @foreach($incomes as $income)
            @foreach($income->allocations as $alloc)
              @php
                $budgetName = $alloc->category ? $alloc->category->name : ucfirst($alloc->type);
                $budgetColors = [
                  'Personale' => 'primary',
                  'Familiare' => 'info',
                  'Extra'     => 'warning',
                  'Risparmi'  => 'success',
                  'Altro'     => 'secondary',
                ];
                $color = $budgetColors[$budgetName] ?? 'secondary';

                $monthKey = \Carbon\Carbon::parse($income->date)->format('Y-m');
                $descRaw  = trim((string)($income->description ?? ''));
                $descKey  = $descRaw !== '' ? mb_strtolower($descRaw, 'UTF-8') : null;
                $key      = $descKey ? $monthKey.'|'.$descKey : null;

                $showTotal = false;
                $totalForGroup = 0;

                if ($key && array_key_exists($key, $groupTotals)) {
                    if (empty($groupShown[$key])) {
                    $showTotal = true;
                    $groupShown[$key] = true; // da ora in poi non lo mostriamo più per questo gruppo
                    $totalForGroup = $groupTotals[$key];
                    }
                }
              @endphp

              <tr>
                <td class="force-show">
                <i class="bi bi-cash-coin text-success me-2"></i>
                {{ $income->description ?? '–' }}
                @if($showTotal)
                    <span class="text-muted ms-1">(tot. € {{ number_format($totalForGroup, 0, ',', '.') }})</span>
                @endif
                </td>
                <td class="force-show">€ {{ number_format($alloc->amount, 0, ',', '.') }}</td>
                <td class="force-show">
                  {{ \Carbon\Carbon::parse($income->date)->locale('it')->isoFormat('MMM YYYY') }}
                </td>
                <td>
                  <span class="badge bg-{{ $color }}">{{ $budgetName }}</span>
                </td>
                <td>—</td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>

      {{-- Paginazione desktop --}}
      <div class="d-flex justify-content-center mt-3">
        {{ $incomes->links() }}
      </div>
    </div>
  </div>

  {{-- VERSIONE MOBILE (card) --}}
  <div class="d-block d-md-none px-2 pt-2">
    @forelse($incomes as $income)
      @foreach($income->allocations as $alloc)
        @php
          $budgetName = $alloc->category ? $alloc->category->name : ucfirst($alloc->type);
          $budgetColors = [
            'Personale' => 'primary',
            'Familiare' => 'info',
            'Extra'     => 'warning',
            'Risparmi'  => 'success',
            'Altro'     => 'secondary',
          ];
          $color = $budgetColors[$budgetName] ?? 'secondary';

        $monthKey = \Carbon\Carbon::parse($income->date)->format('Y-m');
        $descRaw  = trim((string)($income->description ?? ''));
        $descKey  = $descRaw !== '' ? mb_strtolower($descRaw, 'UTF-8') : null;
        $key      = $descKey ? $monthKey.'|'.$descKey : null;

        $showTotal = false;
        $totalForGroup = 0;

        if ($key && array_key_exists($key, $groupTotals)) {
            if (empty($groupShown[$key])) {
            $showTotal = true;
            $groupShown[$key] = true;
            $totalForGroup = $groupTotals[$key];
            }
        }
        @endphp

        <div class="card mb-3 shadow-sm position-relative overflow-hidden border-0">
          <div class="position-absolute top-0 bottom-0 start-0 bg-{{ $color }}" style="width: 5px;"></div>

          <div class="card-body py-3 ps-4 pe-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0 text-nowrap">€ {{ number_format($alloc->amount, 0, ',', '.') }}</h6>
              <small class="text-muted text-end">
                {{ \Carbon\Carbon::parse($income->date)->locale('it')->isoFormat('MMM YYYY') }}
              </small>
            </div>

            <p class="mb-1 fw-semibold">
            <i class="bi bi-cash-coin text-success me-2"></i>
            {{ $income->description ?? '–' }}
            @if($showTotal)
                <span class="text-muted ms-1">(tot. € {{ number_format($totalForGroup, 0, ',', '.') }})</span>
            @endif
            </p>

            <div class="row small text-muted">
              <div class="col-12">
                <span class="badge bg-{{ $color }}">{{ $budgetName }}</span>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    @empty
      <p class="text-center">Nessuna entrata registrata.</p>
    @endforelse

    {{-- Paginazione mobile --}}
    <div class="d-flex justify-content-center mt-3">
      {{ $incomes->links() }}
    </div>
  </div>
</div>



{{-- Modal Nuova Entrata (come definito precedentemente) --}}
<div class="modal fade" id="modalNewIncome" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">


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
                type="number" step="1" name="amount" id="amount"
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

          {{-- Allocazione su bilancio finanziario --}}
<div class="mb-3">
  <label for="wallet_allocation" class="form-label">Allocazione saldo</label>
  <select name="wallet_allocation" id="wallet_allocation" class="form-select">
    <option value="bank" selected>Conto Corrente</option>
    <option value="cash">Contanti</option>
    <option value="none">Non allocare</option>
  </select>
  <small class="text-muted d-block mt-1">
    Scegli dove aggiungere l’importo totale dell’entrata nel bilancio finanziario.
  </small>
</div>

<!-- Ripartizioni -->
<div class="card mb-0">
  <div class="card-header">Ripartizione Budget</div>
  <div class="card-body p-4">
    @php
      // Percentuali di default
      $percentages = [
        'personale' => 11,
        'familiare' => 55,
        'extra'     => 19,
        'risparmi'  => 15,
      ];
    @endphp

            @foreach($categories as $cat)
        <div class="mb-3 row">
            <label class="col-sm-4 col-form-label">{{ $cat->name }}</label>
            <div class="col-sm-8">
            <input
                type="number" step="1"
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

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Responsive -->
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>




<script>
$(function(){
  console.log("Inizializzazione script Entrate");

  // Funzione che applica le % di default
  function applyDefaultAllocations() {
    let total = parseFloat($('#amount').val()) || 0;
    $('.budget-input').each(function(){
      let perc = parseFloat($(this).data('percentage')) || 0;
      let val  = (total * perc/100).toFixed(0);
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

  let difference = total - sum;

  if (Math.abs(difference) > 0.009) {
    $('#allocation-alert')
      .removeClass('d-none')
      .text(`Devi ancora suddividere: € ${difference.toFixed(2)}`);
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


//datatable

$(function () {

$('#income-table').DataTable({
  responsive: true,
  ordering: false,
  pageLength: 20,
  lengthMenu: [5, 10, 25, 50, 100],
  language: {
    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json'
  }
});


});



</script>


@endpush


