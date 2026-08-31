@extends('layouts.app')

@section('title', 'Fantacalcio - Rosa')

@section('navbar-title')
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-clipboard2-check text-info"></i>
    <span class="fw-semibold">Rosa</span>
  </span>
@endsection

@push('styles')
<style>
  .budget-pill { font-weight: 600; }
  .slot-card { border-left: 6px solid transparent; margin-bottom: 1rem; border-radius: .5rem; }
  .slot-title { font-weight: 700; }
  .slot-hint { font-size: .9rem; color: #6c757d; }
  .role-header { background: #343a40; color: #f8f9fa; }
  .chip { font-size: .75rem; padding: .2rem .5rem; border-radius: 999px; display: inline-block; }
  .role-P .slot-card { border-left-color: #0ea5e9; background: rgba(14,165,233,.06); }
  .role-D .slot-card { border-left-color: #22c55e; background: rgba(34,197,94,.06); }
  .role-C .slot-card { border-left-color: #a855f7; background: rgba(168,85,247,.06); }
  .role-A .slot-card { border-left-color: #ef4444; background: rgba(239,68,68,.06); }
  .tag-spesa { min-width: 120px; text-align: right; font-weight: 600; }
  .assigned .slot-title { color: #111827; }
  .assigned .slot-hint { color: #374151; }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  @include('fantacalcio.partials.navbar')

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-fill-check text-primary fs-4"></i>
        <h5 class="mb-0">{{ $team['name'] }}</h5>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary budget-pill">Crediti: {{ $team['budget'] }}</span>
        <span class="badge bg-danger budget-pill">Spesi: {{ $team['spent'] }}</span>
        <span class="badge bg-success budget-pill">Rimanenti: {{ $team['remaining'] }}</span>
        <span class="badge bg-warning text-dark budget-pill">Fondo completamento: {{ $team['completion_floor'] }}</span>
        <span class="badge bg-info text-dark budget-pill">Budget distribuibile: {{ $team['strategic_budget'] }}</span>
      </div>
    </div>
    <div class="card-body border-top">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
        <form method="POST" action="{{ route('fantacalcio.rosa.budget') }}" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
          @csrf
          <div>
            <label for="rosa-budget" class="form-label mb-1">Modifica crediti totali</label>
            <input
              id="rosa-budget"
              type="number"
              name="budget"
              min="1"
              max="9999"
              step="1"
              value="{{ $team['budget'] }}"
              class="form-control"
              required
            >
          </div>
          <button type="submit" class="btn btn-outline-primary">Salva crediti</button>
        </form>

        <form method="POST" action="{{ route('fantacalcio.rosa.reset') }}" onsubmit="return confirm('Azzerare tutta la rosa?');">
          @csrf
          <button type="submit" class="btn btn-outline-danger">Azzera rosa</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header role-header d-flex align-items-center justify-content-between">
      <span class="fw-semibold"><i class="bi bi-list-check me-2"></i> Slots rosa (28)</span>
      <small>Il consigliato è ricalcolato sul budget rimanente degli slot liberi</small>
    </div>

    <div class="card-body">
      @foreach($slots as $slot)
        @php
          $assigned = $assignedByIndex[$slot['index']] ?? null;
          $roleClass = 'role-' . $slot['role_token'];
        @endphp

        <div class="{{ $roleClass }}">
          <div class="card slot-card {{ $assigned ? 'assigned' : '' }}">
            <div class="card-body py-2 d-flex justify-content-between align-items-center gap-3 flex-wrap">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="chip bg-light border">{{ $slot['role_token'] }}</span>
                  <span class="chip bg-light border">{{ $slot['level'] }}</span>
                </div>

                @if($assigned)
                  <div class="slot-title">
                    #{{ $slot['index'] + 1 }} - {{ $assigned['nome'] }}
                    <small class="text-muted">({{ $assigned['roles'] }}, {{ $assigned['team'] }})</small>
                  </div>
                  <div class="slot-hint">
                    Ruolo slot: <strong>{{ $slot['role_token'] }}</strong> - {{ $slot['level'] }}
                  </div>
                @else
                  <div class="slot-title">
                    #{{ $slot['index'] + 1 }} - {{ $slot['title'] }}
                  </div>
                  <div class="slot-hint">
                    Ruolo: <strong>{{ $slot['role_token'] }}</strong> - Livello: <strong>{{ $slot['level'] }}</strong>
                    @if(!empty($slot['hint'])) <br><span>{{ $slot['hint'] }}</span> @endif
                  </div>
                @endif
              </div>

              <div class="tag-spesa">
                @if($assigned)
                  € {{ $assigned['costo'] }}
                @else
                  Consigliato: ~€ {{ number_format($slot['suggested'], 0, ',', '.') }}
                @endif
              </div>

              @if($assigned)
                <button class="btn btn-sm btn-secondary" disabled>Assegnato</button>
              @else
                <button
                  class="btn btn-sm btn-outline-primary select-player-btn"
                  data-role-token="{{ $slot['role_token'] }}"
                  data-slot="{{ $slot['index'] }}"
                >
                  Seleziona
                </button>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

<div class="modal fade" id="modalSelectPlayer" tabindex="-1" aria-labelledby="modalSelectPlayerLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('fantacalcio.rosa.add') }}">
      @csrf
      <input type="hidden" name="external_id" id="modal-external-id">
      <input type="hidden" name="role_token" id="modal-role-token">
      <input type="hidden" name="slot_index" id="modal-slot-index">

      <div class="modal-header">
        <h5 class="modal-title" id="modalSelectPlayerLabel">Seleziona giocatore</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label for="modal-player" class="form-label">Giocatore disponibile</label>
          <input type="text" id="modal-search" class="form-control mb-2" placeholder="Cerca per nome" autocomplete="off">
          <select id="modal-player" class="form-select" required>
            <option value="" selected>Seleziona...</option>
          </select>
          <div class="form-text">
            Filtrato per ruolo classico dello slot e stato disponibile.
          </div>
        </div>

        <div class="mb-3">
          <label for="modal-costo" class="form-label">Crediti pagati</label>
          <input type="number" min="0" step="1" class="form-control" id="modal-costo" name="costo" required>
          <div class="form-text text-info d-none" id="modal-cover-hint">
            Questa squadra è già presente: il portiere sarà una copertura e il costo sarà 0.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary" id="modal-submit" disabled>Aggiungi alla rosa</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
  let bsModal;
  const modal = document.getElementById('modalSelectPlayer');
  const select = document.getElementById('modal-player');
  const extId = document.getElementById('modal-external-id');
  const costo = document.getElementById('modal-costo');
  const coverHint = document.getElementById('modal-cover-hint');
  const submit = document.getElementById('modal-submit');
  const search = document.getElementById('modal-search');

  function resetModal() {
    select.innerHTML = '<option value="" selected>Seleziona...</option>';
    extId.value = '';
    costo.value = '';
    costo.readOnly = false;
    coverHint.classList.add('d-none');
    search.value = '';
    submit.disabled = true;
  }

  function debounce(fn, delay) {
    let t;
    return function() {
      const ctx = this;
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(function() { fn.apply(ctx, args); }, delay);
    };
  }

  function loadPlayers(roleToken, q) {
    const url = new URL("{{ route('fantacalcio.rosa.players') }}", window.location.origin);
    url.searchParams.set('role_token', roleToken || '');
    if (q) url.searchParams.set('q', q);

    select.innerHTML = '<option value="">Caricamento...</option>';

    fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        if (!Array.isArray(list) || list.length === 0) {
          select.innerHTML = '<option value="">Nessun giocatore trovato</option>';
          return;
        }

        const opts = ['<option value="">Seleziona...</option>']
          .concat(list.map(p => `<option value="${p.value}" data-cover="${p.is_cover ? '1' : '0'}">${p.text}</option>`));
        select.innerHTML = opts.join('');
      })
      .catch(() => {
        select.innerHTML = '<option value="">Errore nel caricamento</option>';
      });
  }

  document.querySelectorAll('.select-player-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const roleToken = this.getAttribute('data-role-token');
      const slotIndex = this.getAttribute('data-slot');

      document.getElementById('modal-role-token').value = roleToken;
      document.getElementById('modal-slot-index').value = slotIndex;

      resetModal();
      bsModal = new bootstrap.Modal(modal);
      bsModal.show();
      loadPlayers(roleToken, '');
    });
  });

  search.addEventListener('keyup', debounce(function() {
    const roleToken = document.getElementById('modal-role-token').value;
    loadPlayers(roleToken, search.value.trim());
  }, 300));

  select.addEventListener('change', function() {
    extId.value = this.value || '';
    const selected = this.options[this.selectedIndex];
    const isCover = selected && selected.dataset.cover === '1';
    costo.readOnly = isCover;
    costo.value = isCover ? '0' : '';
    coverHint.classList.toggle('d-none', !isCover);
    submit.disabled = !(extId.value && costo.value !== '' && Number(costo.value) >= 0);
  });

  costo.addEventListener('input', function() {
    submit.disabled = !(extId.value && this.value !== '' && Number(this.value) >= 0);
  });
})();
</script>
@endpush
