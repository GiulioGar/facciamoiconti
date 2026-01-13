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
  .slot-card  { border-left: 6px solid transparent; margin-bottom: 1rem; border-radius: .5rem; }
  .slot-title { font-weight: 700; }
  .slot-hint  { font-size:.9rem; color:#6c757d; }
  .role-header { background:#343a40; color:#f8f9fa; }
  .chip { font-size:.75rem; padding:.2rem .5rem; border-radius: 999px; display:inline-block; }

  /* Colori per RUOLO (bordo e leggero sfondo) */
  .role-Por .slot-card { border-left-color: #0ea5e9; background: rgba(14,165,233,.06); }
  .role-Dc  .slot-card { border-left-color: #22c55e; background: rgba(34,197,94,.06); }
  .role-Ds  .slot-card { border-left-color: #16a34a; background: rgba(22,163,74,.06); }
  .role-Dd  .slot-card { border-left-color: #10b981; background: rgba(16,185,129,.06); }
  .role-E   .slot-card { border-left-color: #38bdf8; background: rgba(56,189,248,.06); }
  .role-M   .slot-card { border-left-color: #64748b; background: rgba(100,116,139,.06); }
  .role-C   .slot-card { border-left-color: #a855f7; background: rgba(168,85,247,.06); }
  .role-T   .slot-card { border-left-color: #f43f5e; background: rgba(244,63,94,.06); }
  .role-W   .slot-card { border-left-color: #f59e0b; background: rgba(245,158,11,.06); }
  .role-A   .slot-card { border-left-color: #fb7185; background: rgba(251,113,133,.06); }
  .role-Pc  .slot-card { border-left-color: #ef4444; background: rgba(239,68,68,.06); }

  .tag-spesa { min-width: 120px; text-align: right; font-weight: 600; }
  .assigned .slot-title { color:#111827; }
  .assigned .slot-hint  { color:#374151; }

  .edit-slot-role i { font-size: 1rem; }
.edit-slot-role:hover { color: var(--bs-primary); text-decoration: none; }

</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Navbar interna --}}
  @include('fantacalcio.partials.navbar')

  {{-- RIEPILOGO SQUADRA (solo totali) --}}
  <div class="card mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-fill-check text-primary fs-4"></i>
        <h5 class="mb-0">{{ $team['name'] }}</h5>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary budget-pill">Crediti: {{ $team['budget'] }}</span>
        <span class="badge bg-danger  budget-pill">Spesi: {{ $team['spent'] }}</span>
        <span class="badge bg-success budget-pill">Rimanenti: {{ $team['remaining'] }}</span>
      </div>
    </div>
  </div>

  {{-- ELENCO SLOT (26) IN COLONNA --}}
  <div class="card mb-4">
    <div class="card-header role-header d-flex align-items-center justify-content-between">
      <span class="fw-semibold"><i class="bi bi-list-check me-2"></i> Slots rosa (26)</span>
      <small class="text-light-50">Il “Consigliato” è rinormalizzato sul budget rimanente degli slot non assegnati</small>
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

{{-- SINISTRA --}}
<div class="flex-grow-1">
  <div class="d-flex align-items-center gap-2 mb-1">
    <span class="chip bg-light border">
      {{ $slot['role_token'] }}
    </span>
    <span class="chip bg-light border">
      {{ $slot['level'] }}
    </span>

    {{-- icona matita per edit ruolo slot --}}
    <button type="button"
            class="btn btn-sm btn-link p-0 ms-1 text-muted edit-slot-role"
            title="Modifica ruolo dello slot"
            data-slot-index="{{ $slot['index'] }}"
            data-current-role="{{ $slot['role_token'] }}">
      <i class="bi bi-pencil-square"></i>
    </button>
  </div>

  @if($assigned)
    <div class="slot-title">
      #{{ $slot['index']+1 }} — {{ $assigned['nome'] }}
      <small class="text-muted">({{ $assigned['roles'] }}, {{ $assigned['team'] }})</small>
    </div>
    <div class="slot-hint">
      Ruolo slot: <strong>{{ $slot['role_token'] }}</strong> — {{ $slot['level'] }}
    </div>
  @else
    <div class="slot-title">
      #{{ $slot['index']+1 }} — {{ $slot['title'] }}
    </div>
    <div class="slot-hint">
      Ruolo: <strong>{{ $slot['role_token'] }}</strong> — Livello: <strong>{{ $slot['level'] }}</strong>
      @if(!empty($slot['hint'])) <br><span>{{ $slot['hint'] }}</span> @endif
    </div>
  @endif
</div>


              {{-- DESTRA: consigliato / costo --}}
              <div class="tag-spesa">
                @if($assigned)
                  € {{ $assigned['costo'] }}
                @else
                  Consigliato: ~€ {{ number_format($slot['suggested'], 0, ',', '.') }}
                @endif
              </div>

              {{-- AZIONE --}}
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
@endsection

{{-- Modal Seleziona Giocatore --}}
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
          <label for="modal-player" class="form-label">Giocatore (solo disponibili)</label>

          {{-- Campo ricerca nome --}}
          <input type="text" id="modal-search" class="form-control mb-2" placeholder="Cerca per nome (es. Lautaro, Buongiorno…)" autocomplete="off">

          <select id="modal-player" class="form-select" required>
            <option value="" selected>Seleziona...</option>
          </select>
          <div class="form-text">
            Filtrato per <strong>ruolo slot</strong> (token) e <strong>stato = 0</strong>. Digita per cercare.
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

{{-- Modal Modifica Ruolo Slot --}}
<div class="modal fade" id="modalEditSlotRole" tabindex="-1" aria-labelledby="modalEditSlotRoleLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="{{ route('fantacalcio.slot.updateRole') }}">
      @csrf
      <input type="hidden" name="slot_index" id="edit-slot-index">

      <div class="modal-header">
        <h5 class="modal-title" id="modalEditSlotRoleLabel">Modifica ruolo slot</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label for="edit-role-token" class="form-label">Nuovo ruolo (token Mantra)</label>
          <select id="edit-role-token" name="new_role_token" class="form-select" required>
            <option value="Por">Por</option>
            <option value="Dc">Dc</option>
            <option value="Ds">Ds</option>
            <option value="Dd">Dd</option>
            <option value="E">E</option>
            <option value="M">M</option>
            <option value="C">C</option>
            <option value="T">T</option>
            <option value="W">W</option>
            <option value="A">A</option>
            <option value="Pc">Pc</option>
          </select>
          <div class="form-text">
            Se cambi il ruolo, la ricerca giocatori per questo slot userà il nuovo token.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva ruolo</button>
      </div>
    </form>
  </div>
</div>


@push('scripts')
<script>
(function(){
  let bsModal;
  const $modal  = document.getElementById('modalSelectPlayer');
  const $sel    = document.getElementById('modal-player');
  const $extId  = document.getElementById('modal-external-id');
  const $costo  = document.getElementById('modal-costo');
  const $submit = document.getElementById('modal-submit');
  const $search = document.getElementById('modal-search');

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

  // carica giocatori via AJAX con (role_token, q)
  function loadPlayers(roleToken, q) {
    const url = new URL("{{ route('fantacalcio.rosa.players') }}", window.location.origin);
    url.searchParams.set('role_token', roleToken || '');
    if (q) url.searchParams.set('q', q);

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

  // Apri modale → set role_token e slot, reset e primo load
  document.querySelectorAll('.select-player-btn').forEach(btn => {
    btn.addEventListener('click', function(){
      const roleToken = this.getAttribute('data-role-token'); // Por, Dc, Ds, Dd, E, M, C, T, W, A, Pc
      const slotIndex = this.getAttribute('data-slot');       // 0..25
      document.getElementById('modal-role-token').value = roleToken;
      document.getElementById('modal-slot-index').value = slotIndex;

      resetModal();
      bsModal = new bootstrap.Modal($modal);
      bsModal.show();

      loadPlayers(roleToken, '');
    });
  });

  // ricerca con debounce mentre digiti
  if ($search) {
    $search.addEventListener('keyup', debounce(function(){
      const roleToken = document.getElementById('modal-role-token').value;
      const q    = $search.value.trim();
      loadPlayers(roleToken, q);
    }, 300));
  }

  // Abilita submit quando selezioni e inserisci costo
  $sel.addEventListener('change', function(){
    document.getElementById('modal-external-id').value = this.value || '';
    $submit.disabled = !(document.getElementById('modal-external-id').value && $costo.value !== '' && Number($costo.value) >= 0);
  });
  $costo.addEventListener('input', function(){
    $submit.disabled = !(document.getElementById('modal-external-id').value && this.value !== '' && Number(this.value) >= 0);
  });
})();


// --- Modifica ruolo slot (icona matita)
document.querySelectorAll('.edit-slot-role').forEach(btn => {
  btn.addEventListener('click', function(){
    const slotIndex   = this.getAttribute('data-slot-index');
    const currentRole = this.getAttribute('data-current-role');

    // setto i campi della modale
    document.getElementById('edit-slot-index').value = slotIndex;

    const sel = document.getElementById('edit-role-token');
    if (sel && currentRole) {
      // se il valore esiste tra le option, selezionalo
      for (const opt of sel.options) {
        opt.selected = (opt.value === currentRole);
      }
    }

    const m = new bootstrap.Modal(document.getElementById('modalEditSlotRole'));
    m.show();
  });
});

</script>
@endpush
