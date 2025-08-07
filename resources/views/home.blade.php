@extends('layouts.app')

@section('title', 'Home')


@section('navbar-title')
<span class="d-flex align-items-center gap-2">
  <i class="bi bi-bank fs-5 text-primary"></i>
  <span class="fw-semibold">Dashboard personale</span>
</span>
@endsection

@section('content')

@php
  // 6 palette coordinate: sfondo header | sfondo badge
  $palettes = [
    ['header' => 'dashboard-header',       'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt1',  'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt2',  'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt3',  'badge' => 'dashboard-badge'],
  ];
  $paletteCount = count($palettes);
  $paletteCount = count($palettes);
@endphp


<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Alert di successo --}}
  @if(session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
    </div>
  @endif

  <div class="row align-items-stretch g-4 dashboard-overview-row">

    {{-- CARD SINISTRA: SITUAZIONE GENERALE --}}
    <div class="col-md-6 mb-4">
  <div class="overview-card-group w-100">

    {{-- CARD PRINCIPALE: SALDO TOTALE --}}
    <div class="card card-summary-main mb-3">
      <div class="card-body text-center">
        <div class="fs-6 text-uppercase text-white mb-1">
          <i class="bi bi-wallet2 me-1"></i> Saldo Totale
        </div>
        <div class="fs-2 fw-bold text-white">
          € {{ number_format($latestTotal, 0, ',', '.') }}
        </div>
      </div>
    </div>

    {{-- CARDS SECONDARIE: LIQUIDITÀ e SCOSTAMENTO --}}
    <div class="d-flex gap-3">
      {{-- Liquidità --}}
      <div class="card flex-fill card-summary-sub">
        <div class="card-body text-center">
          <div class="fs-6 text-uppercase text-muted mb-1">
            <i class="bi bi-droplet-half me-1"></i> Liquidità
          </div>
          <div class="fs-4 fw-bold text-success">
            € {{ number_format($latestLiquid, 0, ',', '.') }}
          </div>
        </div>
      </div>

      {{-- Scostamento --}}
      <div class="card flex-fill card-summary-sub">
        <div class="card-body text-center">
          <div class="fs-6 text-uppercase text-muted mb-1">
            <i class="bi bi-arrow-left-right me-1"></i> Scostamento
          </div>
          @php $diff = $latestTotal - $assignedTotal; @endphp
          <div class="fs-4 fw-bold 
            @if($diff > 0) text-success 
            @elseif($diff < 0) text-danger 
            @else text-muted @endif">
            @if($diff > 0)+@endif{{ number_format($diff, 0, ',', '.') }} €
          </div>
        </div>
      </div>
    </div>

  </div>
</div>



{{-- CARD: Suddivisione Attività --}}

@php
$iconMap = [
  'bank_balance'   => 'bi-bank',
  'other_accounts' => 'bi-credit-card-2-back',
  'cash'           => 'bi-cash-stack',
  'insurances'     => 'bi-file-earmark-lock',
  'investments'    => 'bi-graph-up',
  'debt_credit'    => 'bi-arrow-left-right',
];
@endphp

<div class="col-sm-6 mb-4">
  <div class="card overview-card h-100">
    <div class="card-header">
      <h5 class="card-title mb-0">Suddivisione Importi</h5>
      <div class="update-button-wrapper">
        <button class="btn btn-light text-primary border" data-bs-toggle="modal" data-bs-target="#editBalanceModal">
          Aggiorna
        </button>
      </div>
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
    <li class="mb-3 d-flex justify-content-between align-items-center">
      <span>
        <i class="bi {{ $iconMap[$field] }} me-2 text-primary"></i>
        {{ $label }}
      </span>
      <span class="fw-bold">
        {{ number_format($latestBalance ? $latestBalance->$field : 0, 0, ',', '.') }} €
      </span>
    </li>
  @endforeach
</ul>

    </div>
  </div>
</div>

</div>

    {{-- INCLUDI QUI LA MODALE UNICA --}}
@include('partials.modal-edit-field')

{{-- BUDGET MENSILE --}}
<div class="row">


@php 
    $palettesCount = count($palettes); 

      $categoryIcons = [
    'Casa'       => 'bi-house-door',
    'Spesa'      => 'bi-cart4',
    'Trasporti'  => 'bi-truck',
    'Salute'     => 'bi-heart-pulse',
    'Svago'      => 'bi-controller',
    'Stipendio'  => 'bi-cash-stack',
    'Bollette'   => 'bi-lightning-charge',
    'Viaggi'     => 'bi-airplane',
    'Istruzione' => 'bi-mortarboard',
    'Investimenti' => 'bi-graph-up-arrow',
    'Personale' => 'bi bi-person-arms-up',
    'Familiare' => 'bi bi-house-heart',
    'Extra' => 'bi bi-car-front-fill',
    'Risparmi' => 'bi bi-piggy-bank-fill',


    'Altro'      => 'bi-folder',
  ];

    @endphp

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
            <span>
                @php
                $icon = $categoryIcons[$cat->name] ?? 'bi-folder';
                @endphp
                <i class="bi {{ $icon }} me-2"></i> {{ $cat->name }}
            </span>

            <span class="badge rounded-pill px-2 small {{ $p['badge'] }} no-wrap">
                <b>{{ number_format($total, 0, ',', '.') }}&nbsp;€</b>
            </span>
            </div>


          <div class="card-body p-2">
            <div class="table-responsive">
<table class="table table-sm table-bordered table-hover budget-table mb-0 small">
  <thead>
    <tr class="text-center">
      <th style="color:rgb(0, 0, 0)!important">Mese</th>
      <th class="text-end text-success">
        <span class="bi bi-arrow-down-circle me-1"></span> Entrate
      </th>
      <th class="text-end text-danger">
        <span class="bi bi-arrow-up-circle me-1"></span> Uscite
      </th>
    </tr>
  </thead>
  <tbody>
    @for($m=1; $m<=12; $m++)
      <tr>
        <td>{{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}</td>
        <td class="text-end no-wrap text-success bg-success-subtle">
          {{ number_format($incomeByCategory[$cat->id][$m] ?? 0,0,',','.') }} €
        </td>
        <td class="text-end no-wrap text-danger bg-danger-subtle">
          {{ number_format($expenseByCategory[$cat->id][$m] ?? 0,0,',','.') }} €
        </td>
      </tr>
    @endfor
  </tbody>
  <tfoot class="table-light fw-bold">
    <tr>
      <td>Totale</td>
      <td class="text-end text-success no-wrap">
        {{ number_format(array_sum($incomeByCategory[$cat->id] ?? []), 0, ',', '.') }} €
      </td>
      <td class="text-end text-danger no-wrap">
        {{ number_format(array_sum($expenseByCategory[$cat->id] ?? []), 0, ',', '.') }} €
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

{{-- FINE BUDGET MENSILE --}}

{{-- RIGA 3 --}}
<div class="row">

  <div class="col-md-6 mb-4">
    <div class="card shadow-sm">
        <div class="card-header dashboard-header d-flex justify-content-between align-items-center fs-5">
        <span><i class="bi bi-calendar-week me-2"></i> Resoconto Mensile</span>
        </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover table-bordered mb-0">
            <thead class="dashboard-table-header text-white">
              <tr>
                <th>Mese</th>
                <th class="text-end">Entrate</th>
                <th class="text-end">Uscite</th>
                <th class="text-end">Differenza</th>
              </tr>
            </thead>
            <tbody>
            @for($m = 1; $m <= 12; $m++)
                @php
                $inc  = intval(round($summaryIncomeByMonth[$m]  ?? 0));
                $exp  = intval(round($summaryExpenseByMonth[$m] ?? 0));
                $gain = $summaryGainByMonth[$m];
                @endphp
                <tr>
                <td>{{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}</td>

                <td class="text-end">
                    <span class="badge badge-soft-success">
                    <i class="bi bi-arrow-down-circle me-1"></i>
                    {{ number_format($inc, 0, ',', '.') }} €
                    </span>
                </td>

                <td class="text-end">
                    <span class="badge badge-soft-danger">
                    <i class="bi bi-arrow-up-circle me-1"></i>
                    {{ number_format($exp, 0, ',', '.') }} €
                    </span>
                </td>

                <td class="text-end fw-semibold
                    @if($gain > 0) text-success
                    @elseif($gain < 0) text-danger
                    @else text-warning @endif">
                    @if($gain > 0)+@endif{{ number_format($gain, 0, ',', '.') }} €
                </td>
                </tr>
            @endfor
            </tbody>
            <tfoot class="fw-bold table-light">
              <tr>
                <td>Totale</td>
                <td class="text-end">{{ number_format($totalSummaryIncome, 0, ',', '.') }} €</td>
                <td class="text-end">{{ number_format($totalSummaryExpense, 0, ',', '.') }} €</td>
                <td class="text-end
                    @if($totalSummaryGain > 0) text-success
                    @elseif($totalSummaryGain < 0) text-danger
                    @else text-warning @endif">
                  @if($totalSummaryGain > 0)+@endif{{ number_format($totalSummaryGain, 0, ',', '.') }} €
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

{{-- investimenti--}}
  <div class="col-md-6 mb-4">
    <div class="card shadow-sm border-0 rounded-3">
<div class="card-header dashboard-header-alt3 d-flex justify-content-between align-items-center fs-5">
  <span><i class="bi bi-piggy-bank me-2"></i> Investimenti</span>
  <button
    type="button"
    class="btn btn-sm btn-light text-warning"
    data-bs-toggle="modal"
    data-bs-target="#modal-investimenti">
    Modifica
  </button>
</div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover table-bordered mb-0 small">
            <thead class="table-warning text-dark">
                <tr>
                <th>Categoria</th>
                <th class="text-end">Saldo</th>
                <th class="text-end">Investito</th>
                <th class="text-end">Profit</th>
                </tr>
            </thead>
            <tbody>
              @foreach($investmentSummary as $item)
                <tr>
                  <td>{{ $item['name'] }}</td>
                  <td class="text-end no-wrap">
                    {{ number_format($item['current_balance'], 0, ',', '.') }} €
                  </td>
                  <td class="text-end no-wrap">
                    {{ number_format($item['invested_balance'], 0, ',', '.') }} €
                  </td>
                  @php $p = $item['profit']; @endphp
                  <td class="text-end no-wrap
                      @if($p > 0) text-success
                      @elseif($p < 0) text-danger
                      @else text-warning @endif">
                    @if($p > 0)+@endif{{ number_format($p, 0, ',', '.') }} €
                  </td>
                </tr>
              @endforeach
            </tbody>
            <tfoot class="fw-bold table-light">
              <tr>
                <td>Totale</td>
                <td class="text-end">
                  {{ number_format(collect($investmentSummary)->sum('current_balance'), 0, ',', '.') }} €
                </td>
                <td class="text-end">
                  {{ number_format(collect($investmentSummary)->sum('invested_balance'), 0, ',', '.') }} €
                </td>
                @php $totalProfit = collect($investmentSummary)->sum('profit'); @endphp
                <td class="text-end no-wrap
                    @if($totalProfit > 0) text-success
                    @elseif($totalProfit < 0) text-danger
                    @else text-warning @endif">
                  @if($totalProfit > 0)+@endif{{ number_format($totalProfit, 0, ',', '.') }} €
                </td>
              </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
{{-- fine riga 3--}}

</div>





</div>

@include('partials.modal-investimenti')

@endsection
