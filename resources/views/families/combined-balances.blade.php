@extends('layouts.app')

@push('styles')
<style>
/* Caratteri piccoli per contenuti */
.combined-balances-container .table-lg th,
.combined-balances-container .table-lg td {
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
}
/* Stile header: sfondo chiaro e bordo inferiore più spesso */
.combined-balances-container .table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}
/* Hover riga per leggibilità */
.combined-balances-container .table tbody tr:hover {
    background-color: rgba(0, 140, 189, 0.1);
}
/* Aggiungi ombra leggera e bordi arrotondati */
.combined-balances-container .table {
    background-color: #ffffff;
    border-radius: 0.25rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
/* Margin bottom per spacing */
.combined-balances-container .table-wrapper {
    margin-bottom: 1rem;
}

/* Header verticale “capofamiglia” */
.combined-balances-container .table-right thead th:nth-child(1) {
  background-color: #008cbd;
  color: #fff;
}
/* Header “membro” */
.combined-balances-container .table-right thead th:nth-child(2) {
  background-color: #bc29c6;
  color: #fff;
}
/* Header “totale” neutro ma con bordo colorato */
.combined-balances-container .table-right thead th:nth-child(3) {
  border-left: 2px solid #008cbd;
  border-right: 2px solid #bc29c6;
}

/* Card spese totali */
.card-totals {
  border: none;
  border-radius: .5rem;
  overflow: hidden;
  box-shadow: 0 .25rem .5rem rgba(0,0,0,0.1);
}
/* Intestazione sfumata */
.card-totals .card-header {
  background: linear-gradient(90deg, #008cbd, #bc29c6);
  color: #fff;
  font-weight: 600;
  text-align: center;
  font-size: 1rem;
  padding: .75rem 1rem;
}
/* Tabella interna */
.card-totals .table {
  margin-bottom: 0;
}
.card-totals .table th,
.card-totals .table td {
  vertical-align: middle;
  font-size: .875rem;
  padding: .75rem 1rem;
}
/* Rigatura leggera */
.card-totals .table-striped tbody tr:nth-of-type(odd) {
  background-color: rgba(0, 140, 189, .05);
}
/* Hover più evidente */
.card-totals .table tbody tr:hover {
  background-color: rgba(188, 41, 198, .1);
}
/* Totale allineato a destra e in grassetto */
.card-totals .table td.text-right {
  font-weight: 600;
}

/* Card differenza: sfumatura inversa (viola→azzurro) */
.card-difference {
  border: none;
  border-radius: .5rem;
  overflow: hidden;
  box-shadow: 0 .25rem .5rem rgba(0,0,0,0.1);
}
.card-difference .card-header {
  background: linear-gradient(90deg, #bc29c6 0%, #008cbd 100%) !important;
  color: #fff;
  font-weight: 600;
  font-size: 1rem;
  display: flex;
  align-items: center;
  padding: .75rem 1rem;
}
.card-difference .card-body p {
  font-size: .9rem;
}



</style>
@endpush

@section('content')
<div class="row combined-balances-container">
    <!-- Lato sinistro: tabella su metà larghezza -->
    <div class="col-6">
        <div class="table-wrapper">
            <div class="table-responsive">
                @php
                    // Includi il capofamiglia come primo elemento
                    $users = collect([$family->owner])->merge($family->members);
                @endphp
                <table class="table table-striped table-sm text-nowrap">
                    <thead>
                        <tr>
                            <th class="small">Categoria</th>
                            @foreach($users as $user)
                                <th class="small" style="color: {{ $user->id === $family->owner_id ? '#008cbd' : '#bc29c6' }};">
                                    {{ $user->nickname }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $row)
                            <tr>
                                <td class="small">{{ $row['category'] }}</td>
                                @foreach($users as $user)
                                    <td class="small" style="color: {{ $user->id === $family->owner_id ? '#008cbd' : '#bc29c6' }};">
                                        {{ number_format($row['values'][$user->id] ?? 0, 0, ',', '.') }}€
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Lato destro: spazio per contenuto aggiuntivo -->
<div class="col-6">

  @php
    // Calcolo totale del capofamiglia
    $ownerSum = collect($data)
      ->map(fn($row) => $row['values'][$family->owner->id] ?? 0)
      ->sum();
    // Calcolo totali per ciascun membro
    $memberTotals = $family->members->mapWithKeys(function($member) use ($data) {
      $sum = collect($data)
        ->map(fn($row) => $row['values'][$member->id] ?? 0)
        ->sum();
      return [$member->id => $sum];
    });
  @endphp

  <div class="card card-totals mb-4">
    <div class="card-header">
      SPESE TOTALI
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>{{ $family->owner->nickname }}</th>
              @foreach($family->members as $member)
                <th>{{ $member->nickname }}</th>
              @endforeach
              <th class="text-right">Totale</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ number_format($ownerSum,0,',','.') }}€</td>
              @foreach($family->members as $member)
                <td>{{ number_format($memberTotals[$member->id] ?? 0,0,',','.') }}€</td>
              @endforeach
              <td class="text-right">
                {{ number_format(
                    $ownerSum + $memberTotals->sum(),
                    0, ',', '.'
                  ) }}€
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="row">
  <div class="col-12">
    <div class="card card-difference mb-4">
      <div class="card-header">
        <i class="bi bi-cash-stack me-2"></i> DIFFERENZA SPESE
      </div>
      <div class="card-body">
        @php
          // Determino chi è in debito/credito
          $ownerNick  = $family->owner->nickname;
          $memberNick = $firstMember->nickname;
          $amount     = abs($diff);
        $isDebtor = $diff > 0;
        $verb     = $isDebtor
               ? 'sei in debito con'
               : 'sei in credito verso';
        @endphp

        <p class="mb-0">
          <strong>Hei {{ $ownerNick }},</strong>
          {{ $verb }} <strong>{{ $memberNick }}</strong>
          di <strong>{{ number_format($amount,2,',','.') }}€</strong>!
        </p>
      </div>
    </div>
  </div>
</div>



</div>


</div>
@endsection
