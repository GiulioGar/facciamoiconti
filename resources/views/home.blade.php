@extends('layouts.app')

@section('title', 'Home')

@section('content')

@php
  // 6 palette coordinate: sfondo header | sfondo badge
  $palettes = [
    ['header' => 'bg-primary text-white',   'badge' => 'bg-white text-primary'],
    ['header' => 'bg-success text-white',   'badge' => 'bg-white text-success'],
    ['header' => 'bg-warning text-dark',    'badge' => 'bg-dark text-warning'],
    ['header' => 'bg-info text-dark',       'badge' => 'bg-dark text-info'],
    ['header' => 'bg-secondary text-white', 'badge' => 'bg-white text-secondary'],
    ['header' => 'bg-dark text-white',      'badge' => 'bg-white text-dark'],
  ];
  $paletteCount = count($palettes);
@endphp


<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Alert di successo --}}
  @if(session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
    </div>
  @endif

  <div class="row">
    {{-- CARD SINISTRA: SITUAZIONE GENERALE --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Situazione Generale</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-3 d-flex justify-content-between">
              <span>Saldo Totale</span>
              <span class="fw-bold text-primary">
                € {{ number_format($latestTotal, 2, ',', '.') }}
              </span>
            </li>
            <li class="mb-3 d-flex justify-content-between">
              <span>Liquidità</span>
              <span class="fw-bold text-success">
                € {{ number_format($latestLiquid, 2, ',', '.') }}
              </span>
            </li>
            <li class="d-flex justify-content-between">
              <span>Sgravo</span>
              <span class="fw-bold text-muted">--</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    {{-- CARD DESTRA: SUDDIVISIONE ATTIVITÀ --}}
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Suddivisione Attività</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            @foreach ([
              'bank_balance'   => 'Saldo in Banca',
              'other_accounts' => 'Altri Conti',
              'cash'           => 'Contanti',
              'insurances'     => 'Polizze',
              'investments'    => 'Investimenti',
              'debt_credit'    => 'Debito / Credito',
            ] as $field => $label)
              <li class="mb-3 d-flex justify-content-between">
                <span>{{ $label }}</span>
                <a href="#"
                   data-bs-toggle="modal"
                   data-bs-target="#edit-{{ $field }}"
                   class="fw-bold">
                  € {{ number_format($latestBalance ? $latestBalance->$field : 0, 2, ',', '.') }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </div>

  {{-- BUDGET MENSILE --}}
<div class="row">

{{-- BUDGET MENSILE --}}
@php $palettesCount = count($palettes); @endphp

@foreach($categories->chunk(4) as $chunk)
  <div class="row">
    @foreach($chunk as $cat)
      @php
        $globalIdx = $loop->parent->index * 4 + $loop->index;
        $p = $palettes[$globalIdx % $paletteCount];
        $total = $budgetTotalByCategory[$cat->id] ?? 0;
      @endphp

      <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
        <div class="card shadow-sm border-0 rounded-3">
          {{-- header con nome + badge totale --}}
          <div class="card-header {{ $p['header'] }} d-flex justify-content-between align-items-center fs-5">
            <span>{{ $cat->name }}</span>
            <span class="badge rounded-pill px-2 small {{ $p['badge'] }} no-wrap">
              <b>{{ number_format($total, 0, ',', '.') }}&nbsp;€</b>
            </span>
          </div>

          <div class="card-body p-2">
            <div class="table-responsive">
              <table class="table table-sm table-striped table-bordered table-hover mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>Mese</th>
                    <th class="text-end">Entrate</th>
                    <th class="text-end">Uscite</th>
                  </tr>
                </thead>
                <tbody>
                  @for($m=1; $m<=12; $m++)
                    <tr>
                      <td>{{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}</td>
                      <td class="text-end no-wrap">
                        {{ number_format($incomeByCategory[$cat->id][$m] ?? 0,0,',','.') }}&nbsp;€
                      </td>
                      <td class="text-end no-wrap">
                        {{ number_format($expenseByCategory[$cat->id][$m] ?? 0,0,',','.') }}&nbsp;€
                      </td>
                    </tr>
                  @endfor
                </tbody>
                <tfoot class="table-active fw-semibold">
                  <tr>
                    <td>Totale</td>
                    <td class="text-end no-wrap">
                      {{ number_format(array_sum($incomeByCategory[$cat->id] ?? []),0,',','.') }}&nbsp;€
                    </td>
                    <td class="text-end no-wrap">
                      {{ number_format(array_sum($expenseByCategory[$cat->id] ?? []),0,',','.') }}&nbsp;€
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

        </div>
      </div>
    @endforeach
  </div>
@endforeach



</div>


  {{-- MODALI DINAMICI PER I SALDI --}}
  @foreach ([
    'bank_balance'   => 'Saldo in Banca',
    'other_accounts' => 'Altri Conti',
    'cash'           => 'Contanti',
    'insurances'     => 'Polizze',
    'investments'    => 'Investimenti',
    'debt_credit'    => 'Debito / Credito',
  ] as $field => $label)
    @include('partials.modal-edit-field', [
      'field'  => $field,
      'label'  => $label,
      'value'  => old($field, $balance ? $balance->$field : 0),
      'months' => $balanceMonths,
      'period' => $period,
    ])
  @endforeach

</div>
@endsection
