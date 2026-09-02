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
  /* ----- Chips ----- */
  .chip { font-size: .75rem; padding: .2rem .5rem; border-radius: 999px; display: inline-block; }
  .slot-code-chip {
    font-size: .72rem; padding: .15rem .45rem; border-radius: 999px;
    background: #f0f2f4; border: 1px solid #d5d9e2; color: #566a7f; font-weight: 600; display: inline-block;
  }

  /* ----- Summary: metriche globali ----- */
  .metrics-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
  .metrics-row .metric-card { flex: 1 1 150px; }
  .metric-card { border: 1px solid rgba(67,89,113,.12); border-radius: .45rem; padding: .6rem .8rem; background: #fff; }
  .metric-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #8592a3; margin-bottom: .15rem; }
  .metric-value { font-size: 1.05rem; font-weight: 700; color: #566a7f; line-height: 1.1; }

  /* ----- Summary: reparti ----- */
  .roles-row { display: flex; flex-wrap: wrap; gap: .5rem; }
  .roles-row .role-card { flex: 1 1 160px; }
  .role-card { border: 1px solid rgba(67,89,113,.12); border-radius: .45rem; padding: .6rem .8rem; background: #fff; }
  .role-card-token { font-size: 1rem; font-weight: 700; color: #566a7f; }
  .role-card-sub { font-size: .68rem; text-transform: uppercase; color: #8592a3; }
  .role-card-dl { font-size: .62rem; text-transform: uppercase; letter-spacing: .03em; color: #8592a3; margin-bottom: .05rem; }
  .role-card-dv { font-size: .82rem; font-weight: 600; color: #566a7f; }

  /* ----- Slot card base ----- */
  .slot-card { border-left: 3px solid transparent; margin-bottom: .45rem; border-radius: .4rem; }

  /* Role tint (empty slots) */
  .role-P .slot-card { border-left-color: #0ea5e9; background: rgba(14,165,233,.04); }
  .role-D .slot-card { border-left-color: #22c55e; background: rgba(34,197,94,.03); }
  .role-C .slot-card { border-left-color: #a855f7; background: rgba(168,85,247,.03); }
  .role-A .slot-card { border-left-color: #ef4444; background: rgba(239,68,68,.03); }

  /* Status override (assigned slots — deve venire dopo le role) */
  .slot-card.slot-status-success { border-left-color: #71dd37; background: #fff; }
  .slot-card.slot-status-warning { border-left-color: #ffab00; background: #fff; }
  .slot-card.slot-status-danger  { border-left-color: #ff3e1d; background: #fff; }

  /* ----- Slot typography ----- */
  .slot-label      { font-size: .88rem; color: #566a7f; }
  .slot-name       { font-weight: 600; font-size: .88rem; color: #111827; }
  .slot-team-hint  { font-size: .82rem; color: #6c757d; font-weight: 400; }
  .slot-level-hint { font-size: .7rem; color: #8592a3; }
  .slot-target-sup  { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; color: #8592a3; }
  .slot-target-main { font-size: 1.2rem; font-weight: 700; color: #566a7f; line-height: 1; }
  .slot-max-hint    { font-size: .75rem; color: #8592a3; }
  .econ-line        { font-size: .8rem; color: #566a7f; }

  /* ----- Section label (Portieri) ----- */
  .section-label {
    font-size: .68rem; text-transform: uppercase; letter-spacing: .07em;
    color: #8592a3; font-weight: 700;
  }

  /* ----- Actions strip ----- */
  .actions-strip label.form-label { font-size: .8rem; color: #8592a3; margin-bottom: .2rem; }
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

  {{-- ============================================================ --}}
  {{-- SUMMARY                                                       --}}
  {{-- ============================================================ --}}
  <div class="card mb-4">
    <div class="card-body pb-2">

      {{-- Team name --}}
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-shield-fill-check text-primary fs-5"></i>
        <h5 class="mb-0">{{ $team['name'] }}</h5>
      </div>

      {{-- Riga 1: Metriche globali --}}
      <div class="metrics-row">
        <div class="metric-card">
          <div class="metric-label">Budget totale</div>
          <div class="metric-value">{{ number_format($team['budget'], 0, ',', '.') }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Speso</div>
          <div class="metric-value">{{ number_format($team['spent'], 0, ',', '.') }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Residuo</div>
          <div class="metric-value">{{ number_format($team['remaining'], 0, ',', '.') }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Fondo minimo</div>
          <div class="metric-value">{{ number_format($team['completion_floor'], 0, ',', '.') }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Budget strategico</div>
          <div class="metric-value">{{ number_format($team['strategic_budget'], 0, ',', '.') }}</div>
        </div>
      </div>

      {{-- Riga 2: Reparti --}}
      @php
        $stratBadgeClass = [
          'OPPORTUNISTICO'    => 'bg-info text-white',
          'IN_PIANO'          => 'bg-success text-white',
          'AGGRESSIVO'        => 'bg-warning text-dark',
          'COMPRESSO'         => 'bg-dark text-white',
          'RISCHIO_STRUTTURA' => 'bg-danger text-white',
        ];
        $stratBadgeLabel = [
          'OPPORTUNISTICO'    => 'Opportunistico',
          'IN_PIANO'          => 'In piano',
          'AGGRESSIVO'        => 'Aggressivo',
          'COMPRESSO'         => 'Compresso',
          'RISCHIO_STRUTTURA' => 'Rischio struttura',
        ];
      @endphp
      <div class="roles-row">
        @foreach (['P' => 'Portieri', 'D' => 'Difesa', 'C' => 'Centrocampo', 'A' => 'Attacco'] as $roleToken => $roleName)
          @php
            $roleData = $roleToken === 'P'
              ? ($team['goalkeeper'] ?? [])
              : ($team['roles'][$roleToken] ?? []);
          @endphp
          <div class="role-card">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
              <span class="role-card-token">{{ $roleToken }}</span>
              <span class="role-card-sub">{{ $roleName }}</span>
            </div>
            <div class="row g-1">
              <div class="col-4">
                <div class="role-card-dl">Target</div>
                <div class="role-card-dv">{{ number_format((int) ($roleData['target'] ?? 0), 0, ',', '.') }}</div>
              </div>
              <div class="col-4">
                <div class="role-card-dl">Speso</div>
                <div class="role-card-dv">{{ number_format((int) ($roleData['spent'] ?? $roleData['block_spent'] ?? 0), 0, ',', '.') }}</div>
              </div>
              <div class="col-4">
                <div class="role-card-dl">Residuo</div>
                <div class="role-card-dv">{{ number_format((int) ($roleData['remaining_target'] ?? 0), 0, ',', '.') }}</div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Riga 3: Stati strategici --}}
      @php
        $stratItems = [
          'Globale' => $strategyStatus['global_status']            ?? null,
          'P'       => $strategyStatus['goalkeeper']['status']     ?? null,
          'D'       => $strategyStatus['roles']['D']['status']     ?? null,
          'C'       => $strategyStatus['roles']['C']['status']     ?? null,
          'A'       => $strategyStatus['roles']['A']['status']     ?? null,
        ];
        $stratGlobalDesc = [
          'OPPORTUNISTICO'    => 'Hai accumulato margine rispetto al piano.',
          'IN_PIANO'          => 'La costruzione della rosa è coerente con il piano.',
          'AGGRESSIVO'        => 'Stai spendendo sopra piano, ma la struttura resta sostenibile.',
          'COMPRESSO'         => 'Gli ultimi acquisti stanno comprimendo gli slot ancora aperti.',
          'RISCHIO_STRUTTURA' => 'La struttura della rosa sta diventando fragile.',
        ];
        $stratBadgeTitle = [
          'OPPORTUNISTICO'    => 'Spesa sotto piano, margine disponibile.',
          'IN_PIANO'          => 'Reparto coerente con il piano.',
          'AGGRESSIVO'        => 'Spesa sopra piano, ma ancora sostenibile.',
          'COMPRESSO'         => 'Gli slot futuri sono già ridotti.',
          'RISCHIO_STRUTTURA' => 'La struttura del reparto è fortemente compromessa.',
        ];
      @endphp
      <div class="mt-3 pt-2 border-top d-flex flex-wrap align-items-center gap-3">
        <span class="metric-label">Stato strategico</span>
        @foreach ($stratItems as $itemLabel => $itemStatus)
          <div class="d-flex align-items-center gap-1">
            <span class="metric-label">{{ $itemLabel }}</span>
            @if ($itemStatus)
              <span class="badge rounded-pill {{ $stratBadgeClass[$itemStatus] ?? 'bg-secondary text-white' }}"
                title="{{ $stratBadgeTitle[$itemStatus] ?? '' }}">
                {{ $stratBadgeLabel[$itemStatus] ?? $itemStatus }}
              </span>
            @else
              <span class="text-muted" style="font-size:.82rem;">&mdash;</span>
            @endif
          </div>
        @endforeach
      </div>
      @if (!empty($strategyStatus['global_status']) && isset($stratGlobalDesc[$strategyStatus['global_status']]))
        <div class="mt-1">
          <small class="text-muted">{{ $stratGlobalDesc[$strategyStatus['global_status']] }}</small>
        </div>
      @endif

    </div>

    {{-- Riga 4: Azioni (fascia separata, leggera) --}}
    <div class="card-footer bg-transparent border-top py-2 actions-strip">
      <div class="d-flex flex-wrap gap-3 align-items-end">
        <form method="POST" action="{{ route('fantacalcio.rosa.budget') }}" class="d-flex gap-2 align-items-end">
          @csrf
          <div>
            <label for="rosa-budget" class="form-label mb-1">Crediti totali</label>
            <input
              id="rosa-budget"
              type="number"
              name="budget"
              min="1"
              max="9999"
              step="1"
              value="{{ $team['budget'] }}"
              class="form-control form-control-sm"
              style="width: 90px;"
              required
            >
          </div>
          <button type="submit" class="btn btn-sm btn-outline-secondary">Salva</button>
        </form>

        <form method="POST" action="{{ route('fantacalcio.rosa.reset') }}" onsubmit="return confirm('Azzerare tutta la rosa?');" class="ms-auto">
          @csrf
          <button type="submit" class="btn btn-sm btn-outline-danger">Azzera rosa</button>
        </form>
      </div>
    </div>
  </div>

  {{-- ============================================================ --}}
  {{-- SLOTS                                                         --}}
  {{-- ============================================================ --}}
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center" style="background: #343a40; color: #f8f9fa;">
      <span class="fw-semibold"><i class="bi bi-list-check me-2"></i> Slots rosa (28)</span>
    </div>

    <div class="card-body">
      @php
        $goalkeeperSlots = collect($slots)->filter(fn($s) => $s['role_token'] === 'P')->values();
        $fieldSlots      = collect($slots)->filter(fn($s) => $s['role_token'] !== 'P')->values();
      @endphp

      {{-- ---- Sottosezione Portieri ---- --}}
      <div class="section-label mb-2">Portieri</div>
      <div class="row g-2 mb-4">
        @foreach ($goalkeeperSlots as $slot)
          @php
            $assigned  = $assignedByIndex[$slot['index']] ?? null;
            $slotLabel = $slot['label'] ?? $slot['title'];
            $isBlock   = $assigned && (int) $assigned['costo'] > 0;
          @endphp
          <div class="col-12 col-md-6">
            <div class="role-P">
              <div class="card slot-card mb-0 {{ $assigned ? 'assigned' : '' }}">
                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-2">
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-1 mb-1">
                      <span class="slot-code-chip">{{ $slot['slot_code'] }}</span>
                      @if($assigned)
                        <span class="chip {{ $isBlock ? 'bg-label-info' : 'bg-label-secondary' }}" style="font-size: .7rem;">
                          {{ $isBlock ? 'Blocco' : 'Copertura' }}
                        </span>
                      @endif
                    </div>
                    @if($assigned)
                      <div class="slot-name">{{ $assigned['nome'] }} <span class="slot-team-hint">({{ $assigned['team'] }})</span></div>
                      <div class="econ-line text-muted">Costo {{ number_format((int) $assigned['costo'], 0, ',', '.') }}</div>
                    @else
                      <div class="slot-label">{{ $slotLabel }}</div>
                    @endif
                  </div>
                  @if($assigned)
                    <form method="POST" action="{{ route('fantacalcio.rosa.remove') }}"
                          onsubmit="return confirm('Vuoi rimuovere questo giocatore dalla rosa?');"
                          class="flex-shrink-0">
                      @csrf
                      <input type="hidden" name="external_id" value="{{ $assigned['ext_id'] }}">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Rimuovi</button>
                    </form>
                  @else
                    <button
                      class="btn btn-sm btn-outline-primary select-player-btn flex-shrink-0"
                      data-role-token="{{ $slot['role_token'] }}"
                      data-slot="{{ $slot['index'] }}"
                    >Seleziona</button>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- ---- Campo: D / C / A ---- --}}
      <div class="border-top pt-3">
        @foreach ($fieldSlots as $slot)
          @php
            $assigned        = $assignedByIndex[$slot['index']] ?? null;
            $roleClass       = 'role-' . $slot['role_token'];
            $slotLabel       = $slot['label'] ?? $slot['title'];
            $targetSnapshot  = $assigned['target_snapshot'] ?? null;
            $massimoSnapshot = $assigned['massimo_snapshot'] ?? null;
            $hasStrategicSnapshot = $assigned && $targetSnapshot !== null && $massimoSnapshot !== null;
            $deltaSnapshot   = $hasStrategicSnapshot ? ((int) $assigned['costo'] - (int) $targetSnapshot) : null;
            $deltaLabel      = $deltaSnapshot === null ? null : (($deltaSnapshot > 0 ? '+' : '') . $deltaSnapshot);
            $statusClass     = '';

            if ($hasStrategicSnapshot) {
              if ((int) $assigned['costo'] <= (int) $targetSnapshot) {
                $statusClass = 'slot-status-success';
              } elseif ((int) $assigned['costo'] <= (int) $massimoSnapshot) {
                $statusClass = 'slot-status-warning';
              } else {
                $statusClass = 'slot-status-danger';
              }
            }

            if ($statusClass === 'slot-status-success') {
              $statusBadgeClass = 'bg-label-success';
              $statusBadgeText  = 'Entro target';
            } elseif ($statusClass === 'slot-status-warning') {
              $statusBadgeClass = 'bg-label-warning';
              $statusBadgeText  = 'Entro max';
            } elseif ($statusClass === 'slot-status-danger') {
              $statusBadgeClass = 'bg-label-danger';
              $statusBadgeText  = 'Oltre max';
            } else {
              $statusBadgeClass = '';
              $statusBadgeText  = '';
            }
          @endphp

          <div class="{{ $roleClass }}">
            <div class="card slot-card {{ $assigned ? 'assigned' : '' }} {{ $statusClass }}">
              <div class="card-body py-2 px-3 d-flex align-items-center gap-3">

                {{-- Identita' --}}
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center gap-1 mb-1">
                    <span class="slot-code-chip">{{ $slot['slot_code'] }}</span>
                    @if($assigned && $hasStrategicSnapshot)
                      <span class="chip {{ $statusBadgeClass }}" style="font-size: .7rem;">{{ $statusBadgeText }}</span>
                    @elseif(!$assigned)
                      <span class="slot-level-hint ms-1">{{ $slot['level'] }}</span>
                    @endif
                  </div>

                  @if($assigned)
                    <div class="slot-name">{{ $assigned['nome'] }} <span class="slot-team-hint">({{ $assigned['team'] }})</span></div>
                    @if($hasStrategicSnapshot)
                      <div class="econ-line">
                        Pagato {{ number_format((int) $assigned['costo'], 0, ',', '.') }}
                        &middot; Target {{ number_format((int) $targetSnapshot, 0, ',', '.') }}
                        &middot; Max {{ number_format((int) $massimoSnapshot, 0, ',', '.') }}
                        &middot; {{ $deltaLabel }}
                      </div>
                    @else
                      <div class="econ-line text-muted">Costo {{ number_format((int) $assigned['costo'], 0, ',', '.') }} &middot; Rif. strategico non disp.</div>
                    @endif
                  @else
                    <div class="slot-label">{{ $slotLabel }}</div>
                    @if(!empty($slot['hint']))
                      <div class="slot-team-hint">{{ $slot['hint'] }}</div>
                    @endif
                  @endif
                </div>

                {{-- Target / Max (solo slot libero) --}}
                @if(!$assigned)
                  <div class="text-end flex-shrink-0" style="min-width: 68px;">
                    <div class="slot-target-sup">Target</div>
                    <div class="slot-target-main">{{ number_format((int) ($slot['target'] ?? $slot['suggested'] ?? 0), 0, ',', '.') }}</div>
                    <div class="slot-max-hint">Max {{ number_format((int) ($slot['massimo'] ?? 0), 0, ',', '.') }}</div>
                  </div>
                @endif

                {{-- Pulsante --}}
                @if($assigned)
                  <form method="POST" action="{{ route('fantacalcio.rosa.remove') }}"
                        onsubmit="return confirm('Vuoi rimuovere questo giocatore dalla rosa?');"
                        class="flex-shrink-0">
                    @csrf
                    <input type="hidden" name="external_id" value="{{ $assigned['ext_id'] }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Rimuovi</button>
                  </form>
                @else
                  <button
                    class="btn btn-sm btn-outline-primary select-player-btn flex-shrink-0"
                    data-role-token="{{ $slot['role_token'] }}"
                    data-slot="{{ $slot['index'] }}"
                  >Seleziona</button>
                @endif

              </div>
            </div>
          </div>
        @endforeach
      </div>

    </div>
  </div>

</div>

{{-- Modal (invariato) --}}
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
