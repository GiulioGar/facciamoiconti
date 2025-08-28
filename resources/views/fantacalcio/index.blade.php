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

      <form action="{{ route('fantacalcio.listone.sync') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-arrow-repeat me-1"></i> Aggiorna Lista
        </button>
      </form>
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
  const likeUrl      = id => "{{ url('/fantacalcio/player') }}/"+id+"/like";
  const likeDecUrl   = id => "{{ url('/fantacalcio/player') }}/"+id+"/like/dec";
  const dislikeUrl   = id => "{{ url('/fantacalcio/player') }}/"+id+"/dislike";
  const dislikeDecUrl= id => "{{ url('/fantacalcio/player') }}/"+id+"/dislike/dec";
  const toggleUrl    = id => "{{ url('/fantacalcio/player') }}/"+id+"/toggle-stato";
  const csrf = '{{ csrf_token() }}';

  function renderTitolare(val){
    const v = Number(val);
    if(!v)     return '<i class="bi bi-question-circle text-muted" title="N.D."></i>';
    if(v===1)  return '<i class="bi bi-person-fill text-success"  title="Titolare"></i>';
    if(v===2)  return '<i class="bi bi-shuffle text-warning"      title="Ballottaggio"></i>';
    if(v===3)  return '<i class="bi bi-person-dash text-secondary" title="Riserva"></i>';
    return '<i class="bi bi-question-circle text-muted" title="N.D."></i>';
  }

  // Martello piccolo "clickable" al posto del bottone
  function renderAsta(stato, row){
    const id = row[12];
    const active = Number(stato)===1;
    const cls = active ? 'text-success' : 'text-secondary opacity-75';
    const title = active ? 'All’asta (clic per rimuovere)' : 'Non all’asta (clic per mettere)';
    return `<i class="bi bi-hammer ${cls} icon-asta" data-id="${id}" title="${title}" role="button"></i>`;

  }

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
  [ 7, 'asc' ], // Titolare ASC
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
      // 0 Asta (icona martello piccola)
      { data: 0, className:'text-center', orderable:false, render:(d,type,row)=>renderAsta(d,row) },

      // 1..6  (ID, Ruolo, Mantra, Nome, Squadra, FVM intero)
      { data: 1 },                     // ID
      { data: 2 },                     // Ruolo
      { data: 3 },                     // Mantra
      { data: 4 },                     // Nome
      { data: 5 },                     // Squadra
      { data: 6, className:'text-end' }, // FVM (intero)

      // 7 Titolare (icone)
      { data: 7, className:'text-center', render: d => renderTitolare(d) },

      // 8 2024 (solo valore, niente icona)
      { data: 8, className:'text-center' },

      // 9 Like: click = +1, Alt/Shift = -1
      { data: 9, className:'text-center', orderable:false, render: (d, type, row) => {
          const id = row[12];
          return `<span class="icon-like" data-id="${id}" title="Click = +1 • Alt/Shift = −1" role="button">
                    <i class="bi bi-hand-thumbs-up me-1"></i><strong>${d}</strong>
                  </span>`;
      }},

      // 10 Dislike: click = +1, Alt/Shift = -1
      { data:10, className:'text-center', orderable:false, render: (d, type, row) => {
          const id = row[12];
          return `<span class="icon-dislike" data-id="${id}" title="Click = +1 • Alt/Shift = −1" role="button">
                    <i class="bi bi-hand-thumbs-down me-1"></i><strong>${d}</strong>
                  </span>`;
      }},

      // 11 Punteggio (solo numero, niente icona)
      { data:11, className:'text-center fw-semibold' },
    ],

  responsive: {
    // Clicca la riga per vedere i campi nascosti (ID, FVM) come “card”
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

  // Diciamo a Responsive quali colonne sacrificare per prime
  columnDefs: [
    { responsivePriority: 1,  targets: [4,9,10,11] },    // Tieni visibili: Nome, Like, Dislike, Punteggio
    { responsivePriority: 2,  targets: [0,2,5,7,8] },    // Poi: Asta, Ruolo, Squadra, Titolare, 2024
    { responsivePriority: 100,targets: [1,6] }           // Nascondi presto su mobile: ID, FVM
  ],

  // Colora le righe assegnate (stato=1)
  rowCallback: function(row, data){
    if (Number(data[0]) === 1) $(row).addClass('dt-row-assigned');
    else $(row).removeClass('dt-row-assigned');
  },

    //dom: 'rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-2"lip>',

      rowCallback: function(row, data) {
    // data[0] = "stato" (0/1) come da tuo output server
    if (Number(data[0]) === 1) {
      $(row).addClass('dt-row-assigned');
    } else {
      $(row).removeClass('dt-row-assigned');
    }
  }

  });

  // Filtri → reload
  $('#searchName').on('keyup', debounce(()=>table.ajax.reload(null,false), 300));
  $('#role-classic, #role-mantra').on('change', ()=>table.ajax.reload(null,false));

  // Azioni:
  // Asta (toggle)
  $('#listone-table').on('click', '.icon-asta', function(){
    $.post(toggleUrl($(this).data('id')), {_token: csrf}, ()=>table.ajax.reload(null,false));
  });

  // Like: click = +1, Alt/Shift = -1
  $('#listone-table').on('click', '.icon-like', function(e){
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? likeDecUrl(id) : likeUrl(id);
    $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
  });

  // Dislike: click = +1, Alt/Shift = -1
  $('#listone-table').on('click', '.icon-dislike', function(e){
    const id = $(this).data('id');
    const url = (e.altKey || e.shiftKey) ? dislikeDecUrl(id) : dislikeUrl(id);
    $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
  });
})();


// Asta (toggle)
$('#listone-table').on('click', '.icon-asta', function(e){
  e.stopPropagation();
  $.post(toggleUrl($(this).data('id')), {_token: csrf}, ()=>table.ajax.reload(null,false));
});

// Like
$('#listone-table').on('click', '.icon-like', function(e){
  e.stopPropagation();
  const id = $(this).data('id');
  const url = (e.altKey || e.shiftKey) ? likeDecUrl(id) : likeUrl(id);
  $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
});

// Dislike
$('#listone-table').on('click', '.icon-dislike', function(e){
  e.stopPropagation();
  const id = $(this).data('id');
  const url = (e.altKey || e.shiftKey) ? dislikeDecUrl(id) : dislikeUrl(id);
  $.post(url, {_token: csrf}, ()=>table.ajax.reload(null,false));
});


</script>

<style>
  /* UX: rendiamo chiaramente cliccabili le icone */
  .icon-asta, .icon-like, .icon-dislike { cursor: pointer; user-select: none; }
</style>
@endpush


