@extends('layouts.app')

@section('title', 'Fantacalcio - Home')

@section('navbar-title')
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-trophy-fill text-warning"></i>
    <span class="fw-semibold">Fantacalcio</span>
  </span>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">

  <style>
    #fanta-listone .btn { line-height: 1; }
    #fanta-listone .btn i { vertical-align: middle; }
    #listone-table tbody tr.dt-row-assigned > * { background-color: rgba(var(--bs-danger-rgb), 0.12) !important; }
    #listone-table tbody tr.dt-row-assigned td:first-child { box-shadow: inset 3px 0 0 rgba(var(--bs-danger-rgb), .6); }
    #listone-table tbody tr.dt-row-assigned + tr.child > td.child { background-color: rgba(var(--bs-danger-rgb), 0.12) !important; }
    .titolare-cell { display: inline-flex; align-items: center; gap: .25rem; }
    .titolare-pill {
      display: inline-block;
      min-width: 70px;
      padding: .15rem .5rem;
      text-align: center;
      font-weight: 600;
      border-radius: 999px;
      color: #111;
      border: 1px solid rgba(0,0,0,.05);
    }
    .titolare-btn {
      line-height: 1;
      padding: .1rem .35rem;
      border: 1px solid rgba(0,0,0,.08);
    }
    .titolare-btn:focus { box-shadow: none; }
    .badge.bg-success, .badge.bg-primary, .badge.bg-info { font-weight: 600; }

    @media (max-width: 576px) {
      #listone-table td, #listone-table th {
        font-size: .84rem;
        padding: .35rem .5rem;
        white-space: normal !important;
      }
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_filter,
      .dataTables_wrapper .dataTables_info,
      .dataTables_wrapper .dataTables_paginate {
        font-size: .85rem;
      }
      .bi { font-size: 1rem; }
      #searchName::placeholder { font-size: .9rem; }
      #role-classic { padding-left: .5rem; padding-right: 1.6rem; }
    }
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

  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <span class="d-flex align-items-center">
        <i class="bi bi-table me-2"></i> Listone giocatori
      </span>

      <div class="d-flex align-items-center ms-auto">
        <form action="{{ route('fantacalcio.listone.sync') }}" method="POST" class="m-0">
          @csrf
          <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i> Aggiorna Lista
          </button>
        </form>
        <form action="{{ route('fantacalcio.listone.updateLevels') }}" method="POST" class="m-0 ms-2">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up-arrow me-1"></i> Aggiorna livelli
          </button>
        </form>
        <form action="{{ route('fantacalcio.goat.import') }}" method="POST" enctype="multipart/form-data" class="m-0 ms-2 d-flex align-items-center gap-1">
          @csrf
          <input type="file" name="json" accept=".json" class="form-control form-control-sm" style="max-width:160px" required>
          <button type="submit" class="btn btn-outline-warning btn-sm text-nowrap">
            <i class="bi bi-lightning-fill me-1"></i> Import Goat
          </button>
        </form>
        <form action="{{ route('fantacalcio.esperto2.import') }}" method="POST" enctype="multipart/form-data" class="m-0 ms-2 d-flex align-items-center gap-1">
          @csrf
          <input type="file" name="xlsx" accept=".xlsx,.xls" class="form-control form-control-sm" style="max-width:160px" required>
          <button type="submit" class="btn btn-outline-info btn-sm text-nowrap">
            <i class="bi bi-people-fill me-1"></i> Import GE
          </button>
        </form>
        <form action="{{ route('fantacalcio.stats.import') }}" method="POST" enctype="multipart/form-data" class="m-0 ms-2 d-flex align-items-center gap-1">
          @csrf
          <input type="file" name="xlsx" accept=".xlsx,.xls" class="form-control form-control-sm" style="max-width:130px" required>
          <input type="text" name="season" value="2026-27" class="form-control form-control-sm" style="max-width:70px" required placeholder="2026-27">
          <button type="submit" class="btn btn-outline-success btn-sm text-nowrap">
            <i class="bi bi-bar-chart-fill me-1"></i> Import Stats
          </button>
        </form>
      </div>
    </div>

    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-12 col-md-4">
          <label for="searchName" class="form-label mb-1">Cerca per Nome</label>
          <input id="searchName" type="text" class="form-control" placeholder="Es. Lautaro, Buongiorno..." autocomplete="off">
        </div>

        <div class="col-12 col-md-4">
          <label for="role-classic" class="form-label mb-1">Ruolo</label>
          <select id="role-classic" class="form-select">
            <option value="" selected>Tutti i ruoli</option>
            <option value="P">Portiere (P)</option>
            <option value="D">Difensore (D)</option>
            <option value="C">Centrocampista (C)</option>
            <option value="A">Attaccante (A)</option>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label for="filter-level" class="form-label mb-1">Level</label>
          <select id="filter-level" class="form-select">
            <option value="" selected>Tutti i level</option>
            <option value="5">5 — TOP</option>
            <option value="4">4 — Ottimo</option>
            <option value="3">3 — Medio</option>
            <option value="2">2 — Basso</option>
            <option value="1">1 — Scarso</option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table id="listone-table" class="table table-striped table-hover table-sm align-middle" style="width:100%">
          <thead class="table-dark">
            <tr>
              <th class="text-center">Asta</th>
              <th>Ruolo</th>
              <th>Nome</th>
              <th>Squadra</th>
              <th class="text-end">FVM</th>
              <th class="text-center">Titolare</th>
              <th class="text-center">PV</th>
              <th class="text-end">FM</th>
              <th class="text-center">Score</th>
              <th class="text-center">Level</th>
              <th class="text-center">IA</th>
              <th class="text-center">GE</th>
              <th class="text-center">Like</th>
              <th class="text-center">Dislike</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
(function() {
  function debounce(fn, delay){ let t; return function(){ clearTimeout(t); t=setTimeout(() => fn.apply(this, arguments), delay); }; }

  const likeUrl = id => "{{ url('/fantacalcio/player') }}/" + id + "/like";
  const likeDecUrl = id => "{{ url('/fantacalcio/player') }}/" + id + "/like/dec";
  const dislikeUrl = id => "{{ url('/fantacalcio/player') }}/" + id + "/dislike";
  const dislikeDecUrl = id => "{{ url('/fantacalcio/player') }}/" + id + "/dislike/dec";
  const toggleUrl = id => "{{ url('/fantacalcio/player') }}/" + id + "/toggle-stato";
  const titolareUrl = id => "{{ url('/fantacalcio/listone') }}/" + id + "/titolare";
  const csrf = '{{ csrf_token() }}';

  function gradientFor(p) {
    p = Number(p || 0);
    if (p <= 33) return 'linear-gradient(90deg, #7f1d1d 0%, #fecaca 100%)';
    if (p <= 66) return 'linear-gradient(90deg, #854d0e 0%, #fde68a 100%)';
    return 'linear-gradient(90deg, #bbf7d0 0%, #166534 100%)';
  }

  function renderAsta(stato, row) {
    const id = row[ROW_ID_IDX];
    const active = Number(stato) === 1;
    const cls = active ? 'text-success' : 'text-secondary opacity-75';
    const title = active ? 'All\'asta' : 'Fuori asta';
    return `<i class="bi bi-hammer ${cls} icon-asta" data-id="${id}" title="${title}" role="button"></i>`;
  }

  function renderTitolarePill(val, row) {
    const p = (val == null) ? 0 : parseInt(val, 10);
    const id = row[ROW_ID_IDX];
    const bg = gradientFor(p);
    return `
      <div class="titolare-cell d-inline-flex align-items-center gap-1" data-id="${id}" data-value="${p}">
        <button type="button" class="btn btn-sm btn-light titolare-btn tit-dec" title="-1">-</button>
        <span class="titolare-pill" style="background:${bg};">${p}%</span>
        <button type="button" class="btn btn-sm btn-light titolare-btn tit-inc" title="+1">+</button>
      </div>
    `;
  }

  const ROW_ID_IDX = 14;

  const table = $('#listone-table').DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    searching: false,
    lengthMenu: [10, 25, 50, 100],
    pageLength: 25,
    order: [
      [8, 'desc'],
      [12, 'desc'],
      [2, 'asc']
    ],
    ajax: {
      url: "{{ route('fantacalcio.listone.data') }}",
      data: function(d) {
        d.name        = $('#searchName').val() || '';
        d.role_classic = $('#role-classic').val() || '';
        d.level        = $('#filter-level').val() || '';
      }
    },
    language: { url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/it-IT.json" },
    columns: [
      { data: 0,  className: 'text-center', orderable: false, render: (d, type, row) => renderAsta(d, row) },
      { data: 1 },
      { data: 2 },
      { data: 3 },
      { data: 4,  className: 'text-end' },
      { data: 5,  className: 'text-center', orderable: false, render: (d, type, row) => renderTitolarePill(d, row) },
      { data: 6,  className: 'text-center text-muted', render: d => d !== null ? d : '-' },
      { data: 7,  className: 'text-end fw-semibold', render: d => d !== null ? d : '-' },
      { data: 8,  className: 'text-center fw-semibold', render: d => d !== null ? d : '-' },
      { data: 9,  className: 'text-center', render: (d, type, row) => {
          const lvl = parseInt(d ?? 3, 10);
          const id = row[ROW_ID_IDX];
          const label = {1:'Scarso',2:'Basso',3:'Medio',4:'Ottimo',5:'TOP'}[lvl] || 'Medio';
          const cls = {1:'secondary',2:'secondary',3:'info',4:'primary',5:'success'}[lvl] || 'info';
          return `<span class="badge bg-${cls} cell-level-edit" data-id="${id}" data-level="${lvl}" title="Clic per modificare">${lvl} - ${label}</span>`;
      }},
      { data: 10, className: 'text-center fw-semibold', render: d => d !== null ? d : 'NV' },
      { data: 11, className: 'text-center fw-semibold', render: d => d !== null ? d : 'NV' },
      { data: 12, className: 'text-center', orderable: false, render: (d, type, row) => {
          const id = row[ROW_ID_IDX];
          return `<span class="icon-like" data-id="${id}" title="Click = +1, Alt/Shift = -1" role="button"><i class="bi bi-hand-thumbs-up me-1"></i><strong>${d}</strong></span>`;
      }},
      { data: 13, className: 'text-center', orderable: false, render: (d, type, row) => {
          const id = row[ROW_ID_IDX];
          return `<span class="icon-dislike" data-id="${id}" title="Click = +1, Alt/Shift = -1" role="button"><i class="bi bi-hand-thumbs-down me-1"></i><strong>${d}</strong></span>`;
      }},
    ],
    responsive: {
      details: {
        type: 'inline',
        target: 'tr',
        renderer: function (api, rowIdx, columns) {
          const rows = $.map(columns, function(col) {
            return col.hidden
              ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}"><td class="fw-semibold pe-2">${col.title}:</td><td>${col.data}</td></tr>`
              : '';
          }).join('');
          return rows ? $('<table class="table table-sm table-borderless m-0"><tbody>' + rows + '</tbody></table>') : false;
        }
      },
      breakpoints: [
        { name: 'desktop', width: Infinity },
        { name: 'tablet', width: 1024 },
        { name: 'fablet', width: 768 },
        { name: 'phone', width: 576 }
      ]
    },
    columnDefs: [
      { responsivePriority: 1,   targets: [2, 8, 9] },
      { responsivePriority: 2,   targets: [0, 1, 10, 11, 12, 13] },
      { responsivePriority: 3,   targets: [6, 7] },
      { responsivePriority: 50,  targets: [3, 5] },
      { responsivePriority: 100, targets: [4] }
    ],
    rowCallback: function(row, data) {
      if (Number(data[0]) === 1) $(row).addClass('dt-row-assigned');
      else $(row).removeClass('dt-row-assigned');
    }
  });

  $('#searchName').on('keyup', debounce(() => table.ajax.reload(null, false), 300));
  $('#role-classic').on('change', () => table.ajax.reload(null, false));
  $('#filter-level').on('change', () => table.ajax.reload(null, false));

  $('#listone-table').on('click', '.icon-asta', function(e) {
    e.stopPropagation();
    $.post(toggleUrl($(this).data('id')), {_token: csrf}, () => table.ajax.reload(null, false));
  });

  $('#listone-table').on('click', '.icon-like', function(e) {
    e.stopPropagation();
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? likeDecUrl(id) : likeUrl(id);
    $.post(url, {_token: csrf}, () => table.ajax.reload(null, false));
  });

  $('#listone-table').on('click', '.icon-dislike', function(e) {
    e.stopPropagation();
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? dislikeDecUrl(id) : dislikeUrl(id);
    $.post(url, {_token: csrf}, () => table.ajax.reload(null, false));
  });

  $('#listone-table').on('click', '.tit-inc, .tit-dec', function(e) {
    e.stopPropagation();
    const wrap = this.closest('.titolare-cell');
    const id = wrap.getAttribute('data-id');
    const delta = this.classList.contains('tit-inc') ? 1 : -1;

    fetch(titolareUrl(id), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ delta })
    })
    .then(r => r.json())
    .then(json => { if (json.ok) table.ajax.reload(null, false); });
  });

  $('#listone-table').on('dblclick', '.titolare-pill', function(e) {
    e.stopPropagation();
    const wrap = this.closest('.titolare-cell');
    const id = wrap.getAttribute('data-id');
    const cur = parseInt(wrap.getAttribute('data-value') || '0', 10);
    const val = prompt('Imposta titolarità (0-100):', cur);
    if (val === null) return;
    const vNum = Math.max(0, Math.min(100, parseInt(val, 10) || 0));

    fetch(titolareUrl(id), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ value: vNum })
    })
    .then(r => r.json())
    .then(json => { if (json.ok) table.ajax.reload(null, false); });
  });

  $('#listone-table').on('click', '.cell-level-edit', function(e) {
    e.stopPropagation();
    const id = this.getAttribute('data-id');
    const cur = parseInt(this.getAttribute('data-level') || '3', 10);
    const val = prompt('Imposta Level (1=Scarso .. 5=TOP):', cur);
    if (val === null) return;

    const lvl = Math.max(1, Math.min(5, parseInt(val, 10) || cur));

    fetch("{{ route('fantacalcio.listone.updateLevel', ['id'=>'__ID__']) }}".replace('__ID__', id), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ level: lvl })
    })
    .then(r => r.json())
    .then(json => {
      if (json.ok) table.ajax.reload(null, false);
      else alert(json.message || 'Errore durante il salvataggio del livello.');
    });
  });

})();
</script>

<style>
  .icon-asta, .icon-like, .icon-dislike { cursor: pointer; user-select: none; }
</style>
@endpush
