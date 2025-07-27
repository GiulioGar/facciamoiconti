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
                € {{ number_format($latestTotal, 0, ',', '.') }}
              </span>
            </li>
            <li class="mb-3 d-flex justify-content-between">
              <span>Liquidità</span>
              <span class="fw-bold text-success">
                € {{ number_format($latestLiquid, 0, ',', '.') }}
              </span>
            </li>
            <li class="d-flex justify-content-between">
            <span>Scostamento</span>
            @php
                $diff = $latestTotal - $assignedTotal;
            @endphp

            @if($diff == 0)
                <span class="fw-bold text-success">OK</span>
            @elseif($diff > 0)
                <span class="fw-bold text-success">
                +{{ number_format($diff, 0, ',', '.') }} €
                </span>
            @else
                <span class="fw-bold text-danger">
                {{ number_format($diff, 0, ',', '.') }} €
                </span>
            @endif
            </li>

          </ul>
        </div>
      </div>
    </div>

{{-- CARD: Suddivisione Attività --}}
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
            <span class="fw-bold">
              {{ number_format($latestBalance ? $latestBalance->$field : 0, 0, ',', '.') }} €
            </span>
          </li>
        @endforeach
      </ul>

      {{-- UNICO PULSANTE PER APRIRE LA MODALE --}}
      <div class="mt-3 text-end">
        <button class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#editBalanceModal">
          Aggiorna Saldo
        </button>
      </div>
    </div>
  </div>
</div>

  </div>

    {{-- INCLUDI QUI LA MODALE UNICA --}}
@include('partials.modal-edit-field')

{{-- BUDGET MENSILE --}}
<div class="row">


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

{{-- FINE BUDGET MENSILE --}}

{{-- RIGA 3 --}}
<div class="row">

  <div class="col-md-6 mb-4">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">Resoconto Mensile</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped table-hover table-bordered mb-0">
            <thead class="table-primary text-white">
              <tr>
                <th>Mese</th>
                <th class="text-end">Entrate</th>
                <th class="text-end">Uscite</th>
                <th class="text-end">Risultato</th>
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
                  <td>
                    {{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}
                  </td>
                  <td class="text-end">{{ number_format($inc, 0, ',', '.') }} €</td>
                  <td class="text-end">{{ number_format($exp, 0, ',', '.') }} €</td>
                  <td class="text-end
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
<div class="card-header bg-warning text-dark fs-5 d-flex justify-content-between align-items-center">
  <span>Investimenti</span>
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
