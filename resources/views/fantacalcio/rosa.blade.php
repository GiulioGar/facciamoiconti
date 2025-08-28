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
  .slot-card  { border-left: 6px solid transparent; margin-bottom: 1rem; } /* spazio tra card */
  .slot-title { font-weight: 700; }
  .slot-hint  { font-size:.9rem; color:#6c757d; }
  .role-header { background:#343a40; color:#f8f9fa; }

  /* Colori per RUOLO sulle card (bordo sinistro) */
  .role-P .slot-card { border-left-color: #0ea5e9; } /* azzurro */
  .role-D .slot-card { border-left-color: #22c55e; } /* verde */
  .role-C .slot-card { border-left-color: #a855f7; } /* viola */
  .role-A .slot-card { border-left-color: #f59e0b; } /* arancio */

  .tag-spesa { min-width: 90px; text-align: right; font-weight: 600; }
  .assigned .slot-title { color:#111827; }
  .assigned .slot-hint  { color:#374151; }
</style>
@endpush


@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Navbar interna --}}
  @include('fantacalcio.partials.navbar')

  {{-- RIEPILOGO SQUADRA --}}
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
      </div>
    </div>
  </div>

  {{-- PORTIERI --}}
<div class="card mb-4 role-P">
  {{-- header P come sopra --}}
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center role-header">
    <span class="fw-semibold"><i class="bi bi-person-badge me-2"></i> {{ $roles['P']['label'] }}</span>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-light text-dark">Consigliato: {{ $adviceByRole['P']['suggested'] }}</span>
      <span class="badge bg-warning text-dark">Speso: {{ $adviceByRole['P']['spent'] }}</span>
      <span class="badge bg-success">Rimanente: {{ $adviceByRole['P']['remaining'] }}</span>
    </div>
  </div>
<br/>
  <div class="card-body">
@foreach($adviceByRole['P']['slot_suggested'] as $i => $suggest)
  @php $assigned = $assignedMap['P'][$i] ?? null; @endphp
  <div class="card slot-card {{ $assigned ? 'assigned' : '' }}">
    <div class="card-body py-2 d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <div class="flex-grow-1">
        @if($assigned)
          <div class="slot-title">{{ $assigned['nome'] }} — {{ $assigned['roles'] }}</div>
          <div class="slot-hint">{{ $assigned['team'] }}</div>
        @else
          <div class="slot-title">Slot {{ $i+1 }} — Consigliata: ~{{ $suggest }}</div>
          <div class="slot-hint">{{ $guidelines['P'][$i]['hint'] ?? '' }}</div>
        @endif
      </div>

      <div class="tag-spesa">
        @if($assigned) € {{ $assigned['costo'] }} @endif
      </div>

      @if($assigned)
        <button class="btn btn-sm btn-secondary" disabled>Assegnato</button>
      @else
        <button class="btn btn-sm btn-outline-primary select-player-btn" data-role="P" data-slot="{{ $i }}">Seleziona</button>
      @endif
    </div>
  </div>
@endforeach

  </div>
</div>


  {{-- DIFENSORI --}}
<div class="card mb-4 role-D">
  {{-- header come sopra ma con chiave D --}}
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center role-header">
    <span class="fw-semibold"><i class="bi bi-shield-lock me-2"></i> {{ $roles['D']['label'] }}</span>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-light text-dark">Consigliato: {{ $adviceByRole['D']['suggested'] }}</span>
      <span class="badge bg-warning text-dark">Speso: {{ $adviceByRole['D']['spent'] }}</span>
      <span class="badge bg-success">Rimanente: {{ $adviceByRole['D']['remaining'] }}</span>
    </div>
  </div>
<br/>
<div class="card-body">
    @foreach($guidelines['D'] as $i => $slot)
      @php
        $assigned = $assignedMap['D'][$i] ?? null;
        $suggest  = $adviceByRole['D']['slot_suggested'][$i] ?? 0;
      @endphp

      <div class="card slot-card {{ $assigned ? 'assigned' : '' }}">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
              @if($assigned)
                <div class="slot-title">{{ $assigned['nome'] }} — {{ $assigned['roles'] }}</div>
                <div class="slot-hint">{{ $assigned['team'] }}</div>
              @else
                <div class="slot-title">Slot {{ $i+1 }} — Consigliata: ~{{ $suggest }}</div>
                <div class="slot-hint">{{ $slot['hint'] }}</div>
              @endif
            </div>

            <div class="tag-spesa">
              @if($assigned)
                € {{ $assigned['costo'] }}
              @endif
            </div>

            @if($assigned)
              <button class="btn btn-sm btn-secondary" disabled>Assegnato</button>
            @else
              <button class="btn btn-sm btn-outline-primary select-player-btn" data-role="D" data-slot="{{ $i }}">
                Seleziona
              </button>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

</div>


  {{-- CENTROCAMPISTI --}}
<div class="card mb-4 role-C">
  {{-- header come sopra ma con chiave D --}}
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center role-header">
    <span class="fw-semibold"><i class="bi bi-shield-lock me-2"></i> {{ $roles['C']['label'] }}</span>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-light text-dark">Consigliato: {{ $adviceByRole['C']['suggested'] }}</span>
      <span class="badge bg-warning text-dark">Speso: {{ $adviceByRole['C']['spent'] }}</span>
      <span class="badge bg-success">Rimanente: {{ $adviceByRole['C']['remaining'] }}</span>
    </div>
  </div>
<br/>
<div class="card-body">
    @foreach($guidelines['C'] as $i => $slot)
      @php
        $assigned = $assignedMap['C'][$i] ?? null;
        $suggest  = $adviceByRole['C']['slot_suggested'][$i] ?? 0;
      @endphp

      <div class="card slot-card {{ $assigned ? 'assigned' : '' }}">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
              @if($assigned)
                <div class="slot-title">{{ $assigned['nome'] }} — {{ $assigned['roles'] }}</div>
                <div class="slot-hint">{{ $assigned['team'] }}</div>
              @else
                <div class="slot-title">Slot {{ $i+1 }} — Consigliata: ~{{ $suggest }}</div>
                <div class="slot-hint">{{ $slot['hint'] }}</div>
              @endif
            </div>

            <div class="tag-spesa">
              @if($assigned)
                € {{ $assigned['costo'] }}
              @endif
            </div>

            @if($assigned)
              <button class="btn btn-sm btn-secondary" disabled>Assegnato</button>
            @else
              <button class="btn btn-sm btn-outline-primary select-player-btn" data-role="C" data-slot="{{ $i }}">
                Seleziona
              </button>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

</div>


  {{-- ATTACCANTI --}}
<div class="card mb-4 role-A">
  {{-- header come sopra ma con chiave A --}}
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center role-header">
    <span class="fw-semibold"><i class="bi bi-shield-lock me-2"></i> {{ $roles['A']['label'] }}</span>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-light text-dark">Consigliato: {{ $adviceByRole['A']['suggested'] }}</span>
      <span class="badge bg-warning text-dark">Speso: {{ $adviceByRole['A']['spent'] }}</span>
      <span class="badge bg-success">Rimanente: {{ $adviceByRole['A']['remaining'] }}</span>
    </div>
  </div>
<br/>
<div class="card-body">
    @foreach($guidelines['A'] as $i => $slot)
      @php
        $assigned = $assignedMap['A'][$i] ?? null;
        $suggest  = $adviceByRole['A']['slot_suggested'][$i] ?? 0;
      @endphp

      <div class="card slot-card {{ $assigned ? 'assigned' : '' }}">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1">
              @if($assigned)
                <div class="slot-title">{{ $assigned['nome'] }} — {{ $assigned['roles'] }}</div>
                <div class="slot-hint">{{ $assigned['team'] }}</div>
              @else
                <div class="slot-title">Slot {{ $i+1 }} — Consigliata: ~{{ $suggest }}</div>
                <div class="slot-hint">{{ $slot['hint'] }}</div>
              @endif
            </div>

            <div class="tag-spesa">
              @if($assigned)
                € {{ $assigned['costo'] }}
              @endif
            </div>

            @if($assigned)
              <button class="btn btn-sm btn-secondary" disabled>Assegnato</button>
            @else
              <button class="btn btn-sm btn-outline-primary select-player-btn" data-role="A" data-slot="{{ $i }}">
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
@endsection

{{-- Modal Seleziona Giocatore --}}
<div class="modal fade" id="modalSelectPlayer" tabindex="-1" aria-labelledby="modalSelectPlayerLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('fantacalcio.rosa.add') }}">
      @csrf
      <input type="hidden" name="external_id" id="modal-external-id">
      <input type="hidden" name="role" id="modal-role">
      <input type="hidden" name="slot_index" id="modal-slot-index">

      <div class="modal-header">
        <h5 class="modal-title" id="modalSelectPlayerLabel">Seleziona giocatore</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
        <label for="modal-player" class="form-label">Giocatore (solo disponibili)</label>

        {{-- Campo ricerca nome --}}
        <input type="text" id="modal-search" class="form-control mb-2" placeholder="Cerca per nome (es. Lautaro, Buongiorno…)" autocomplete="off">

        <select id="modal-player" class="form-select" required>
            <option value="" selected>Seleziona...</option>
        </select>
        <div class="form-text">
            Filtrato per <strong>ruolo</strong> e <strong>stato = 0</strong>. Digita per cercare.
        </div>
        </div>

        <div class="mb-3">
          <label for="modal-costo" class="form-label">Crediti pagati</label>
          <input type="number" min="0" step="1" class="form-control" id="modal-costo" name="costo" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary" id="modal-submit" disabled>Aggiungi alla rosa</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function(){
  let bsModal;
  const $modal = document.getElementById('modalSelectPlayer');
  const $role  = document.getElementById('modal-role');
  const $sel   = document.getElementById('modal-player');
  const $extId = document.getElementById('modal-external-id');
  const $costo = document.getElementById('modal-costo');
  const $submit= document.getElementById('modal-submit');
  const $search= document.getElementById('modal-search');

  function resetModal() {
    $sel.innerHTML = '<option value="" selected>Seleziona...</option>';
    $extId.value = '';
    $costo.value = '';
    if ($search) $search.value = '';
    $submit.disabled = true;
  }

  // debounce helper
  function debounce(fn, delay) {
    let t; return function(){ const c=this,a=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(c,a),delay); };
  }

  // carica giocatori via AJAX con (role, q)
  function loadPlayers(role, q) {
    const url = new URL("{{ route('fantacalcio.rosa.players') }}", window.location.origin);
    url.searchParams.set('role', role || '');
    if (q) url.searchParams.set('q', q);

    // feedback di caricamento
    $sel.innerHTML = '<option value="">Caricamento...</option>';

    fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => {
        if (!Array.isArray(list) || list.length === 0) {
          $sel.innerHTML = '<option value="">Nessun giocatore trovato</option>';
          return;
        }
        const opts = ['<option value="">Seleziona...</option>']
          .concat(list.map(p => `<option value="${p.value}">${p.text}</option>`));
        $sel.innerHTML = opts.join('');
      })
      .catch(() => {
        $sel.innerHTML = '<option value="">Errore nel caricamento</option>';
      });
  }

  // Apri modale → set role, reset e primo load
  document.querySelectorAll('.select-player-btn').forEach(btn => {
    btn.addEventListener('click', function(){
    const role = this.getAttribute('data-role'); // P/D/C/A
    const slot = this.getAttribute('data-slot'); // 0,1,2,...
    document.getElementById('modal-role').value = role;
    document.getElementById('modal-slot-index').value = slot;
      $role.value = role;
      resetModal();

      bsModal = new bootstrap.Modal($modal);
      bsModal.show();

      loadPlayers(role, ''); // primo caricamento senza filtro nome
    });
  });

  // ricerca con debounce mentre digiti
  if ($search) {
    $search.addEventListener('keyup', debounce(function(){
      const role = $role.value;
      const q    = $search.value.trim();
      loadPlayers(role, q);
    }, 300));
  }

  // Abilita submit quando selezioni e inserisci costo
  $sel.addEventListener('change', function(){
    $extId.value = this.value || '';
    $submit.disabled = !($extId.value && $costo.value !== '' && Number($costo.value) >= 0);
  });
  $costo.addEventListener('input', function(){
    $submit.disabled = !($extId.value && this.value !== '' && Number(this.value) >= 0);
  });
})();
</script>
@endpush

