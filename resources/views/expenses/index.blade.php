@extends('layouts.app')

@section('title', 'Uscite')


@section('navbar-title')
<span class="d-flex align-items-center gap-2">
  <i class="bi bi-box-arrow-in-left fs-5 text-primary"></i>
  <span class="fw-semibold">Le tue spese</span>
</span>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Button per aprire modal Nuova Spesa --}}
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNewExpense">
      + Nuova Spesa
    </button>
  </div>

{{-- VERSIONE TABELLA per desktop --}}
<div class="card d-none d-md-block">
  <div class="card-header">Elenco Uscite</div>
  <div class="card-body table-responsive p-3">
    <table id="expense-table" class="table table-modern table-hover w-100">
      <thead>
        <tr>
          <th>Spesa</th>
          <th>Importo</th>
          <th>Mese</th>
          <th>Budget</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
            @forelse($expenses as $expense)
            @php
                switch($expense->budgetCategory->slug) {
                case 'personale': $badgeColor = 'primary'; break;
                case 'familiare': $badgeColor = 'info'; break;
                case 'extra':     $badgeColor = 'warning'; break;
                default:          $badgeColor = 'secondary';
                }

                $slug = $expense->expenseCategory->slug ?? null;
                $iconData = category_icons()[$slug] ?? ['icon' => 'tag', 'color' => 'text-danger'];

            @endphp

            <tr>
                <td><i class="bi bi-{{ $iconData['icon'] }} {{ $iconData['color'] }} me-1"></i> {{ $expense->expenseCategory->name }}</td>
                <td>€ {{ number_format($expense->amount, 0, ',', '.') }}</td>
                <td>{{ \Carbon\Carbon::parse($expense->date)->locale('it')->isoFormat('MMM YYYY') }}</td>
                <td><span class="badge bg-{{ $badgeColor }}">{{ $expense->budgetCategory->name }}</span></td>
                <td>{{ $expense->note ?? '–' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">Nessuna spesa registrata.</td>
            </tr>
            @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- VERSIONE CARD per mobile --}}
<div class="d-block d-md-none">
  @forelse($expenses as $expense)
    @php
        switch($expense->budgetCategory->slug) {
          case 'personale': $badgeColor = 'primary'; break;
          case 'familiare': $badgeColor = 'info'; break;
          case 'extra':     $badgeColor = 'warning'; break;
          default:          $badgeColor = 'secondary';
        }
    
                $slug = $expense->expenseCategory->slug ?? null;
                $iconData = category_icons()[$slug] ?? ['icon' => 'credit-card', 'color' => 'text-danger'];

    @endphp
    <div class="card mb-3 shadow-sm position-relative overflow-hidden border-0">
      {{-- Linea colorata a sinistra --}}
      <div class="position-absolute top-0 bottom-0 start-0 bg-{{ $badgeColor }}" style="width: 5px;"></div>

      <div class="card-body py-3 ps-4 pe-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0 text-nowrap">€ {{ number_format($expense->amount, 0, ',', '.') }}</h6>
          <small class="text-muted text-end">{{ \Carbon\Carbon::parse($expense->date)->locale('it')->isoFormat('MMM YYYY') }}</small>
        </div>

        <p class="mb-1 fw-semibold">
          <i class="bi bi-{{ $iconData['icon'] }} {{ $iconData['color'] }} me-1"></i> {{ $expense->expenseCategory->name }} 
        </p>

        <div class="row small text-muted">
          <div class="col-6">
            <strong>Budget</strong><br>
           
          </div>
          <div class="col-6">
             <span class="badge bg-{{ $badgeColor }}">{{ $expense->budgetCategory->name }}</span>
          </div>
          @if($expense->note)
          <div class="col-12 mt-2">
            <strong>Note</strong><br>
            <span class="text-dark">{{ $expense->note }}</span>
          </div>
          @endif
        </div>
      </div>
    </div>
  @empty
    <p class="text-center">Nessuna spesa registrata.</p>
  @endforelse
</div>


  
</div>

{{-- Modal Nuova Spesa --}}
<div class="modal fade" id="modalNewExpense" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('expenses.store') }}">
      @csrf
      <input type="hidden" name="family_id" value="{{ $family->id }}">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nuova Spesa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          @php
            // Mappatura budget per slug
            $budgetMap = [
              'personale' => $budgetCats->firstWhere('slug','personale')->id,
              'extra'     => $budgetCats->firstWhere('slug','extra')->id,
              'familiare' => $budgetCats->firstWhere('slug','familiare')->id,
            ];
            // Note suggestions per categoria (distinte)
            $noteSuggestions = \App\Models\Expense::where('family_id',$family->id)
                                ->whereNotNull('note')
                                ->distinct()
                                ->get(['expense_category_id','note'])
                                ->groupBy('expense_category_id')
                                ->map(fn($grp) => $grp->pluck('note')->unique()->values());
          @endphp

          {{-- Categoria (etichettata Spesa) --}}
          <div class="mb-3">
            <label class="form-label">Spesa</label>
            <select name="expense_category_id" id="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
              <option value="">Seleziona...</option>
              @foreach($expCats as $ec)
                <option value="{{ $ec->id }}" {{ old('expense_category_id') == $ec->id ? 'selected' : '' }}>
                  {{ $ec->name }}
                </option>
              @endforeach
            </select>
            @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Importo --}}
          <div class="mb-3">
            <label class="form-label">Importo (€)</label>
            <input type="number" step="1" name="amount" id="expense-amount"
                   class="form-control @error('amount') is-invalid @enderror"
                   value="{{ old('amount') }}" required>
            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Mese --}}
          <div class="mb-3">
            <label class="form-label">Mese</label>
            <select name="date" class="form-select @error('date') is-invalid @enderror" required>
              @foreach($months as $m)
                <option value="{{ $m }}" {{ old('date') == $m ? 'selected' : '' }}>
                  {{ \Carbon\Carbon::createFromFormat('Y-m', $m)->locale('it')->isoFormat('MMMM YYYY') }}
                </option>
              @endforeach
            </select>
            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Budget --}}
          <div class="mb-3">
            <label class="form-label">Budget</label>
            <select name="budget_category_id" id="budget_category_id" class="form-select @error('budget_category_id') is-invalid @enderror" required>
              @foreach($budgetCats as $bc)
                <option value="{{ $bc->id }}" {{ old('budget_category_id') == $bc->id ? 'selected' : '' }}>
                  {{ $bc->name }}
                </option>
              @endforeach
            </select>
            @error('budget_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Note con suggerimenti --}}
          <div class="mb-3">
            <label class="form-label">Note</label>
            <input type="text" name="note" list="note-list" id="note-input"
                   class="form-control @error('note') is-invalid @enderror"
                   value="{{ old('note') }}">
            <datalist id="note-list"></datalist>
            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-danger">Salva</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
  console.log("Inizializzazione controlli modale Uscite");

  // Mappature categorie -> budget
  var budgetMap = @json($budgetMap);
  var personaleCats = [1,5,6,14,15,22,23];
  var extraCats     = [2,3,4,17,24];
  var familiareCats = [7,8,9,10,11,12,13,16,18,19,20,21];
  var catToBudget = {};
  personaleCats.forEach(id => catToBudget[id] = budgetMap.personale);
  extraCats.forEach(id     => catToBudget[id] = budgetMap.extra);
  familiareCats.forEach(id => catToBudget[id] = budgetMap.familiare);

  // Note suggestions per categoria
  var noteSuggestions = @json($noteSuggestions);

  // Al cambio di Spesa (categoria)
  $('#expense_category_id').on('change', function(){
    var catId = parseInt($(this).val());
    // Imposto il budget corrispondente
    if(catToBudget[catId]){
      $('#budget_category_id').val(catToBudget[catId]);
    }
    // Aggiorno i suggerimenti note
    updateNoteList(catId);
  });

  function updateNoteList(catId){
    var list = $('#note-list');
    list.empty();
    var suggestions = noteSuggestions[catId] || [];
    suggestions.forEach(function(txt){
      list.append('<option value="'+ txt +'">');
    });
  }

  // All'apertura del modal, trigger initial
  $('#modalNewExpense').on('shown.bs.modal', function(){
    var sel = parseInt($('#expense_category_id').val());
    if(sel) $('#expense_category_id').trigger('change');
  });
});
</script>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
  $(function () {
$('#expense-table').DataTable({
  responsive: {
    details: {
      type: 'inline',
      target: 'tr'
    }
  },
  pageLength: 20,
  ordering: false,
  lengthMenu: [5, 10, 25, 50, 100],
  language: {
    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/it-IT.json'
  }
});



  });
</script>


@endpush
