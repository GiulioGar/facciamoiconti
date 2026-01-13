@extends('layouts.app')

@section('title', 'Storico')

@section('navbar-title')
<span class="d-flex align-items-center gap-2">
  <i class="bi bi-clock-history fs-5 text-primary"></i>
  <span class="fw-semibold">Storico annuale</span>
</span>
@endsection

@section('content')

<div class="alert alert-primary d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-calendar-event fs-5"></i>
  <div>
    <strong>Anno selezionato:</strong>
    <span class="fs-5">{{ $year }}</span>
  </div>
</div>


@php
  $palettes = [
    ['header' => 'dashboard-header',      'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt1', 'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt2', 'badge' => 'dashboard-badge'],
    ['header' => 'dashboard-header-alt3', 'badge' => 'dashboard-badge'],
  ];
  $paletteCount = count($palettes);

  $categoryIcons = [
    'Casa'        => 'bi-house-door',
    'Spesa'       => 'bi-cart4',
    'Trasporti'   => 'bi-truck',
    'Salute'      => 'bi-heart-pulse',
    'Svago'       => 'bi-controller',
    'Stipendio'   => 'bi-cash-stack',
    'Bollette'    => 'bi-lightning-charge',
    'Viaggi'      => 'bi-airplane',
    'Istruzione'  => 'bi-mortarboard',
    'Investimenti'=> 'bi-graph-up-arrow',
    'Personale'   => 'bi-person-arms-up',
    'Familiare'   => 'bi-house-heart',
    'Extra'       => 'bi-car-front-fill',
    'Risparmi'    => 'bi-piggy-bank-fill',
    'Altro'       => 'bi-folder',
  ];
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- SELETTORE ANNO --}}
  <form method="GET" class="mb-4">
    <div class="d-flex align-items-center gap-3">
      <label class="fw-semibold">Anno:</label>

<select name="year"
        class="form-select w-auto fw-bold text-primary border-primary shadow-sm"
        onchange="this.form.submit()">
  @for($y = $maxYear; $y >= $minYear; $y--)
<option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>
    {{ $y }}
</option>
  @endfor
</select>

    </div>
  </form>

  {{-- ========================= --}}
  {{-- BUDGET MENSILE CATEGORIE --}}
  {{-- ========================= --}}
  @foreach($categories->chunk(4) as $chunk)
    <div class="row">
      @foreach($chunk as $cat)
        @php
          $idx = $loop->parent->index * 4 + $loop->index;
          $p = $palettes[$idx % $paletteCount];
        @endphp

        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
          <div class="card shadow-sm border-0 rounded-3">

            <div class="card-header {{ $p['header'] }} d-flex justify-content-between align-items-center fs-5">
              <span>
                <i class="bi {{ $categoryIcons[$cat->name] ?? 'bi-folder' }} me-2"></i>
                {{ $cat->name }}
              </span>
            </div>

            <div class="card-body p-2">
              <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0 small">
<thead class="table-light text-dark">
  <tr class="text-center">
    <th class="text-dark">Mese</th>
    <th class="text-end text-success">Entrate</th>
    <th class="text-end text-danger">Uscite</th>
  </tr>
</thead>
                  <tbody>
                    @for($m = 1; $m <= 12; $m++)
                      <tr>
                        <td>
                          {{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}
                        </td>
                        <td class="text-end text-success">
                          {{ number_format($incomeByCategory[$cat->id][$m] ?? 0, 0, ',', '.') }} €
                        </td>
                        <td class="text-end text-danger">
                          {{ number_format($expenseByCategory[$cat->id][$m] ?? 0, 0, ',', '.') }} €
                        </td>
                      </tr>
                    @endfor
                  </tbody>
                  <tfoot class="fw-bold table-light">
                    <tr>
                      <td>Totale</td>
                      <td class="text-end text-success">
                        {{ number_format(array_sum($incomeByCategory[$cat->id] ?? []), 0, ',', '.') }} €
                      </td>
                      <td class="text-end text-danger">
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

  {{-- ================= --}}
  {{-- RESOCONTO MENSILE --}}
  {{-- ================= --}}
  <div class="row mt-4">
    <div class="col-md-6">
      <div class="card shadow-sm">
<div class="card-header dashboard-header fs-5 d-flex justify-content-between align-items-center">
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-calendar-week"></i>
    Resoconto Mensile {{ $year }}
  </span>

  @if($comparisonEnabled)
    <span class="badge bg-light text-dark border border-info-subtle d-flex align-items-center gap-2 px-3 py-2">
      <i class="bi bi-arrow-left-right text-info"></i>
      <span class="fw-semibold">Confronto con {{ $previousYear }}</span>
    </span>
  @endif
</div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered mb-0">
            <thead class="dashboard-table-header text-white">
            <tr>
            <th>Mese</th>

            <th class="text-end">{{ $year }} Entrate</th>
            <th class="text-end">{{ $year }} Uscite</th>
            <th class="text-end">{{ $year }} Δ</th>

            @if($comparisonEnabled)
                <th class="text-end">{{ $previousYear }} Δ</th>
                <th class="text-end">Variazione</th>
            @endif
            </tr>
            </thead>

<tbody>
@for($m = 1; $m <= 12; $m++)
@php
  $currGain = $summaryGainByMonth[$m] ?? 0;
  $prevGain = $comparisonEnabled ? ($prevSummaryGainByMonth[$m] ?? 0) : 0;
  $delta    = $comparisonEnabled ? ($currGain - $prevGain) : 0;
@endphp

<tr>
  <td>{{ \Carbon\Carbon::create()->month($m)->locale('it')->isoFormat('MMMM') }}</td>

  <td class="text-end no-wrap">
    {{ number_format($summaryIncomeByMonth[$m] ?? 0, 0, ',', '.') }} €
  </td>

  <td class="text-end no-wrap">
    {{ number_format($summaryExpenseByMonth[$m] ?? 0, 0, ',', '.') }} €
  </td>

  <td class="text-end no-wrap fw-semibold
      @if($currGain > 0) text-success
      @elseif($currGain < 0) text-danger
      @else text-warning @endif">
    @if($currGain > 0)+@endif{{ number_format($currGain, 0, ',', '.') }} €
  </td>

  @if($comparisonEnabled)
    <td class="text-end no-wrap text-muted">
      @if($prevGain > 0)+@endif{{ number_format($prevGain, 0, ',', '.') }} €
    </td>

<td class="text-end no-wrap fw-bold
    @if($delta > 0) text-success
    @elseif($delta < 0) text-danger
    @else text-warning @endif">

  @if($delta > 0)
    <i class="bi bi-arrow-up-right me-1"></i>
  @elseif($delta < 0)
    <i class="bi bi-arrow-down-right me-1"></i>
  @endif

  @if($delta > 0)+@endif{{ number_format($delta, 0, ',', '.') }} €
</td>
  @endif
</tr>
@endfor
</tbody>

            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>
@endsection
