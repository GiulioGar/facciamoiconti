@extends('layouts.app')

@section('title', 'Home')

@section('content')
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
  <div class="card mb-4">
    <div class="card-header">Budget Mensile</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered text-center">
        <thead>
          <tr>
            <th>Mese</th>
            @foreach($categories as $cat)
              <th>{{ $cat->name }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($yearMonths as $key => $label)
            <tr>
              <td>{{ $label }}</td>
              @foreach($categories as $cat)
                <td>€ {{ number_format($grid[$key][$cat->slug] ?? 0, 2, ',', '.') }}</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td>Totale annuale</td>
            @foreach($categories as $cat)
              <td>€ {{ number_format($totalsByCat[$cat->slug] ?? 0, 2, ',', '.') }}</td>
            @endforeach
          </tr>
          <tr class="fw-bold">
            <td>Entrate complessive</td>
            <td colspan="{{ $categories->count() }}">
              € {{ number_format($historicalTotal, 2, ',', '.') }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
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
