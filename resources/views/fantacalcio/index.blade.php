@extends('layouts.app')

@section('title', 'Fantacalcio - Home')

@section('navbar-title')
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-trophy-fill text-warning"></i>
    <span class="fw-semibold">Fantacalcio</span>
  </span>
@endsection

@push('styles')
  {{-- Includi solo se non già presenti globalmente --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">


  <style>
  #fanta-listone .btn { line-height: 1; }
  #fanta-listone .btn i { vertical-align: middle; }

    /* Riga assegnata: tinta rosso chiaro */
  #listone-table tbody tr.dt-row-assigned > * {
    background-color: rgba(var(--bs-danger-rgb), 0.12) !important;
  }
  /* Accento laterale (facoltativo, utile con table-striped) */
  #listone-table tbody tr.dt-row-assigned td:first-child {
    box-shadow: inset 3px 0 0 rgba(var(--bs-danger-rgb), .6);
  }

  /* Se usi DataTables Responsive: colora anche la riga "child" */
  #listone-table tbody tr.dt-row-assigned + tr.child > td.child {
    background-color: rgba(var(--bs-danger-rgb), 0.12) !important;
  }

  /* Mobile tuning */
@media (max-width: 576px) {
  /* riduci font in cells + header */
  #listone-table td, #listone-table th {
    font-size: .84rem;
    padding: .35rem .5rem; /* più stretto */
    white-space: normal !important; /* consenti a Nome/Squadra di andare a capo */
  }

  /* stringi un po’ gli input/controlli DataTables */
  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dataTables_info,
  .dataTables_wrapper .dataTables_paginate {
    font-size: .85rem;
  }

  /* icone più piccole */
  .bi { font-size: 1rem; }
}

/* Mantieni l’effetto “riga assegnata” */
#listone-table tbody tr.dt-row-assigned > * {
  background-color: rgba(var(--bs-danger-rgb), 0.12) !important;
}
#listone-table tbody tr.dt-row-assigned td:first-child {
  box-shadow: inset 3px 0 0 rgba(var(--bs-danger-rgb), .6);
}
#listone-table tbody tr.dt-row-assigned + tr.child > td.child {
  background-color: rgba(var(--bs-danger-rgb), 0.12) !important;
}

@media (max-width: 576px){
  #searchName::placeholder { font-size: .9rem; }
  #role-classic, #role-mantra { padding-left: .5rem; padding-right: 1.6rem; }
}

 .titolare-cell {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }
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

</style>

@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Navbar interna --}}
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
  </div>
</div>


    <div class="card-body">
      {{-- FILTRI --}}
<div class="row g-2 mb-3">
  <div class="col-12 col-md-4">
    <label for="searchName" class="form-label mb-1">Cerca per Nome</label>
    <input id="searchName" type="text" class="form-control" placeholder="Es. Lautaro, Buongiorno..." autocomplete="off">
  </div>

  <div class="col-12 col-md-4">
    <label for="role-classic" class="form-label mb-1">Ruolo Classic</label>
    <select id="role-classic" class="form-select">
      <option value="" selected>Ruolo Classic</option>
      <option value="P">Portiere (P)</option>
      <option value="D">Difensore (D)</option>
      <option value="C">Centrocampista (C)</option>
      <option value="A">Attaccante (A)</option>
    </select>
    <div class="form-text">Filtra per macro-ruoli del gioco Classic.</div>
  </div>

  <div class="col-12 col-md-4">
    <label for="role-mantra" class="form-label mb-1">Ruolo Mantra</label>
    <select id="role-mantra" class="form-select">
      <option value="" selected>Ruolo Mantra</option>
      <option value="Por">Portiere (Por)</option>
      <option value="Dc">Difensore centrale (Dc)</option>
      <option value="B">Braccetto (B)</option>
      <option value="Dd">Difensore destro (Dd)</option>
      <option value="Ds">Difensore sinistro (Ds)</option>
      <option value="E">Esterno (E)</option>
      <option value="M">Mediano (M)</option>
      <option value="C">Centrale (C)</option>
      <option value="W">Ala (W)</option>
      <option value="T">Trequartista (T)</option>
      <option value="A">Attaccante (A)</option>
      <option value="Pc">Punta centrale (Pc)</option>
    </select>
    <div class="form-text">Se selezioni Mantra, il filtro Classic viene ignorato.</div>
  </div>
</div>
      {{-- TABELLA --}}
      <div class="table-responsive">
        <table id="listone-table" class="table table-striped table-hover table-sm align-middle" style="width:100%">
<thead class="table-dark">
  <tr>
    <th class="text-center">Asta</th>        <!-- martello toggle stato -->
    <th>ID</th>
    <th>Ruolo</th>
    <th>Mantra</th>
    <th>Nome</th>
    <th>Squadra</th>
    <th class="text-end">FVM</th>
    <th class="text-center">Titolare</th>   <!-- icona calciatore -->
    <th class="text-center">2024</th>       <!-- mv24 -->
    <th class="text-center">👍</th>         <!-- like -->
    <th class="text-center">👎</th>         <!-- dislike -->
    <th class="text-center">Punteggio</th>  <!-- (FVM*mv24)+(like-dislike) -->
    <th class="text-center">Categoria</th>
<th class="text-end">Crediti</th>
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
  function debounce(fn, delay){ let t; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), delay); }; }

  // URL azioni
  const likeUrl        = id => "{{ url('/fantacalcio/player') }}/"+id+"/like";
  const likeDecUrl     = id => "{{ url('/fantacalcio/player') }}/"+id+"/like/dec";
  const dislikeUrl     = id => "{{ url('/fantacalcio/player') }}/"+id+"/dislike";
  const dislikeDecUrl  = id => "{{ url('/fantacalcio/player') }}/"+id+"/dislike/dec";
  const toggleUrl      = id => "{{ url('/fantacalcio/player') }}/"+id+"/toggle-stato";
  const titolareUrl    = id => "{{ url('/fantacalcio/listone') }}/"+id+"/titolare"; // 👈 NEW
  const csrf = '{{ csrf_token() }}';

  // Gradiente per il valore titolare (0–100)
  function gradientFor(p) {
    p = Number(p||0);
    if (p <= 33) {
      // rosso scuro -> rosso chiaro
      return 'linear-gradient(90deg, #7f1d1d 0%, #fecaca 100%)';
    } else if (p <= 66) {
      // giallo scuro -> giallo chiaro
      return 'linear-gradient(90deg, #854d0e 0%, #fde68a 100%)';
    } else {
      // verde chiaro -> verde scuro
      return 'linear-gradient(90deg, #bbf7d0 0%, #166534 100%)';
    }
  }

  // Martello piccolo "clickable" al posto del bottone
  function renderAsta(stato, row){
    const id = row[ROW_ID_IDX];
    const active = Number(stato)===1;
    const cls = active ? 'text-success' : 'text-secondary opacity-75';
    const title = active ? 'All’asta (clic per rimuovere)' : 'Non all’asta (clic per mettere)';
    return `<i class="bi bi-hammer ${cls} icon-asta" data-id="${id}" title="${title}" role="button"></i>`;
  }

  // Renderer titolare con pill + ±
  function renderTitolarePill(val, row){
    const p  = (val == null) ? 0 : parseInt(val, 10);
    const id = row[ROW_ID_IDX];
    const bg = gradientFor(p);
    return `
      <div class="titolare-cell d-inline-flex align-items-center gap-1" data-id="${id}" data-value="${p}">
        <button type="button" class="btn btn-sm btn-light titolare-btn tit-dec" title="-1">−</button>
        <span class="titolare-pill" style="background:${bg};">${p}%</span>
        <button type="button" class="btn btn-sm btn-light titolare-btn tit-inc" title="+1">+</button>
      </div>
    `;
  }

const ROW_ID_IDX = 14; // indice dell'ID DB tecnico nel dataset (dopo l'aggiunta delle 2 nuove colonne)

  const table = $('#listone-table').DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    searching: false,
    lengthMenu: [10,25,50,100],
    pageLength: 25,
    order: [
      [11, 'desc'], // Punteggio DESC
      [ 9, 'desc'], // Like DESC
      [ 7, 'desc' ], // Titolare ASC
      [ 4, 'asc' ]  // Nome ASC
    ],
    ajax: {
      url: "{{ route('fantacalcio.listone.data') }}",
      data: function(d){
        d.name         = $('#searchName').val() || '';
        d.role_classic = $('#role-classic').val() || '';
        d.role_mantra  = $('#role-mantra').val() || '';
      }
    },
    language: { url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/it-IT.json" },
    columns: [
      // 0 Asta (icona martello)
      { data: 0, className:'text-center', orderable:false, render:(d,type,row)=>renderAsta(d,row) },

      // 1..6  (ID, Ruolo, Mantra, Nome, Squadra, FVM intero)
      { data: 1 },                       // ID
      { data: 2 },                       // Ruolo
      { data: 3 },                       // Mantra
      { data: 4 },                       // Nome
      { data: 5 },                       // Squadra
      { data: 6, className:'text-end' }, // FVM (intero)

      // 7 Titolare (pill gradiente con ±)
      { data: 7, className:'text-center', orderable:false, render: (d,type,row)=>renderTitolarePill(d,row) },

      // 8 2024
      { data: 8, className:'text-center' },

      // 9 Like
      { data: 9, className:'text-center', orderable:false, render: (d, type, row) => {
          const id = row[ROW_ID_IDX];
          return `<span class="icon-like" data-id="${id}" title="Click = +1 • Alt/Shift = −1" role="button">
                    <i class="bi bi-hand-thumbs-up me-1"></i><strong>${d}</strong>
                  </span>`;
      }},

      // 10 Dislike
      { data:10, className:'text-center', orderable:false, render: (d, type, row) => {
          const id = row[ROW_ID_IDX];
          return `<span class="icon-dislike" data-id="${id}" title="Click = +1 • Alt/Shift = −1" role="button">
                    <i class="bi bi-hand-thumbs-down me-1"></i><strong>${d}</strong>
                  </span>`;
      }},

      // 11 Punteggio
      { data:11, className:'text-center fw-semibold' },

          // 12 Level (badge)
{ data: 12, className:'text-center', orderable:true, searchable:true,
  render: (d, type, row) => {
    const lvl   = parseInt(d ?? 3, 10);
    const id    = row[ROW_ID_IDX]; // assicurati che ROW_ID_IDX=14
    const label = {1:'Scarso',2:'Basso',3:'Medio',4:'Ottimo',5:'TOP'}[lvl] || 'Medio';
    const cls   = {1:'secondary',2:'secondary',3:'info',4:'primary',5:'success'}[lvl] || 'info';
    return `<span class="badge bg-${cls} cell-level-edit" data-id="${id}" data-level="${lvl}" title="Clic per modificare">${lvl} - ${label}</span>`;
  }
},



// 13 Crediti consigliati (clic per edit)
{ data: 13, className:'text-end', render: (d, type, row) => {
    const id = row[ROW_ID_IDX];
    const val = (d === null || d === '—') ? '' : parseInt(d,10);
    const display = (val === '' ? '—' : val);
    return `<span class="cell-credits-edit" data-id="${id}" data-value="${val}" title="Clic per modificare crediti">${display}</span>`;
}},

    ],



    responsive: {
      details: {
        type: 'inline',
        target: 'tr',
        renderer: function (api, rowIdx, columns) {
          const rows = $.map(columns, function (col) {
            return col.hidden
              ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                   <td class="fw-semibold pe-2">${col.title}:</td>
                   <td>${col.data}</td>
                 </tr>`
              : '';
          }).join('');
          return rows ? $('<table class="table table-sm table-borderless m-0"><tbody>'+rows+'</tbody></table>') : false;
        }
      },
      breakpoints: [
        { name: 'desktop', width: Infinity },
        { name: 'tablet',  width: 1024 },
        { name: 'fablet',  width: 768 },
        { name: 'phone',   width: 576 }
      ]
    },

    columnDefs: [
  { responsivePriority: 1,   targets: [4,9,10,11] },   // Nome, Like/Dislike, Punteggio
  { responsivePriority: 2,   targets: [0,2,5,7,13] },   // Asta, Ruolo, Squadra, Titolare, 2024
  { responsivePriority: 50,  targets: [12,8] },       // 👈 Level + Crediti consigliati
  { responsivePriority: 100, targets: [1,6] }          // ID, FVM (nascondi presto)
    ],

    rowCallback: function(row, data){
      // data[0] = "stato" (0/1)
      if (Number(data[0]) === 1) $(row).addClass('dt-row-assigned');
      else $(row).removeClass('dt-row-assigned');
    }
  });

  // Filtri → reload
  $('#searchName').on('keyup', debounce(()=>table.ajax.reload(null,false), 300));
  $('#role-classic, #role-mantra').on('change', ()=>table.ajax.reload(null,false));

  // --- Azioni: Asta (toggle)
  $('#listone-table').on('click', '.icon-asta', function(e){
    e.stopPropagation();
    $.post(toggleUrl($(this).data('id')), {_token: csrf}, ()=>table.ajax.reload(null,false));
  });

  // --- Azioni: Like
  $('#listone-table').on('click', '.icon-like', function(e){
    e.stopPropagation();
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? likeDecUrl(id) : likeUrl(id);
    $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
  });

  // --- Azioni: Dislike
  $('#listone-table').on('click', '.icon-dislike', function(e){
    e.stopPropagation();
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? dislikeDecUrl(id) : dislikeUrl(id);
    $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
  });

  // --- Azioni: Titolare ±1
  $('#listone-table').on('click', '.tit-inc, .tit-dec', function (e) {
    e.stopPropagation();
    const wrap = this.closest('.titolare-cell');
    const id   = wrap.getAttribute('data-id');
    const isInc = this.classList.contains('tit-inc');
    const delta = isInc ? 1 : -1;

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

  // --- Azione: doppio click sul pill per set diretto
  $('#listone-table').on('dblclick', '.titolare-pill', function (e) {
    e.stopPropagation();
    const wrap = this.closest('.titolare-cell');
    const id   = wrap.getAttribute('data-id');
    const cur  = parseInt(wrap.getAttribute('data-value') || '0', 10);
    const val  = prompt('Imposta titolarità (0–100):', cur);
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

  // --- Editing inline del livello
$('#listone-table').on('click', '.cell-level-edit', function (e) {
  e.stopPropagation();
  const id  = this.getAttribute('data-id');
  const cur = parseInt(this.getAttribute('data-level') || '3', 10);
  const val = prompt('Imposta Level (1=Scarso .. 5=TOP):', cur);
  if (val === null) return;

  const lvl = Math.max(1, Math.min(5, parseInt(val, 10) || cur));

  fetch("{{ route('fantacalcio.listone.updateLevel', ['id'=>'__ID__']) }}".replace('__ID__', id), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ level: lvl })
  })
  .then(r => r.json())
  .then(json => {
    if (json.ok) {
      $('#listone-table').DataTable().ajax.reload(null, false);
    } else {
      alert(json.message || 'Errore durante il salvataggio del livello.');
    }
  });
});


// --- Editing inline dei crediti consigliati
$('#listone-table').on('click', '.cell-credits-edit', function (e) {
  e.stopPropagation();
  const id  = this.getAttribute('data-id');
  const cur = this.getAttribute('data-value');
  const val = prompt('Imposta crediti consigliati (1..2500) — lascia vuoto per nessun valore:', cur || '');
  if (val === null) return;

  // Consenti vuoto (=> null) oppure intero 1..2500
  let payload;
  if (val.trim() === '') {
    payload = { recommended_credits: null };
  } else {
    const n = parseInt(val, 10);
    if (isNaN(n) || n < 1 || n > 2500) {
      alert('Valore non valido. Inserisci un intero tra 1 e 2500, oppure lascia vuoto.');
      return;
    }
    payload = { recommended_credits: n };
  }

  fetch("{{ route('fantacalcio.listone.updateCredits', ['id'=>'__ID__']) }}".replace('__ID__', id), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(json => {
    if (json.ok) $('#listone-table').DataTable().ajax.reload(null, false);
    else alert(json.message || 'Errore durante il salvataggio dei crediti.');
  });
});

})();
</script>

<style>
  /* UX: rendiamo chiaramente cliccabili le icone */
  .icon-asta, .icon-like, .icon-dislike { cursor: pointer; user-select: none; }

  /* Pill e micro-bottoni titolare */
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
</style>
@endpush



