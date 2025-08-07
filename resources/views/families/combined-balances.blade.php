@extends('layouts.app')

@push('styles')
<style>
/* === Responsive layout per sezioni principali === */
.combined-balances-container {
  display: flex;
  flex-direction: row;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .combined-balances-container {
    flex-direction: column !important;
  }
}

/* === Tabelle migliorate === */
.combined-balances-container .table th,
.combined-balances-container .table td {
  font-size: 0.875rem;
  white-space: nowrap;
  vertical-align: middle;
}

.combined-balances-container .table thead th {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
  font-weight: 600;
}

.combined-balances-container .table tbody tr:hover {
  background-color: rgba(0, 140, 189, 0.1);
}

.table-wrapper {
  background-color: #ffffff;
  border-radius: 0.5rem;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  overflow-x: auto;
  padding: 1rem;
}

/* Card: Spese Totali */
.card-totals,
.card-difference {
  border: none;
  border-radius: .5rem;
  overflow: hidden;
  box-shadow: 0 .25rem .5rem rgba(0,0,0,0.1);
  margin-bottom: 1.5rem;
}

.card-totals .card-header,
.card-difference .card-header {
  background: linear-gradient(90deg, #008cbd, #bc29c6);
  color: white;
  font-weight: 600;
  font-size: 1rem;
  text-align: center;
  padding: .75rem 1rem;
}

.card-difference .card-header {
  background: linear-gradient(90deg, #bc29c6, #008cbd) !important;
}

.card-totals .table td,
.card-totals .table th,
.card-difference .card-body p {
  font-size: 0.875rem;
}

/* Differenza testo */
.card-difference .fs-5 {
  font-size: 1.05rem;
}

.card-difference .fs-3 {
  font-size: 1.3rem;
}

/* Mobile ottimizzazioni */
@media (max-width: 576px) {
  .card-header {
    font-size: 0.95rem;
    padding: 0.6rem 0.9rem;
  }

  .card-difference .fs-5 {
    font-size: 0.95rem !important;
  }

  .card-difference .fs-3 {
    font-size: 1.1rem !important;
  }

  .table th,
  .table td {
    padding: 0.5rem 0.6rem;
  }
}
</style>
@endpush

@section('content')


<div class="combined-balances-container">

  <!-- 🟦 SINISTRA: Tabella spese per categoria e utente -->
  <div class="flex-fill">
    <div class="table-wrapper">
      <div class="table-responsive">
        @php
          $users = collect([$family->owner])->merge($family->members);
        @endphp
        <table id="combi-table" class="table table-striped table-sm text-nowrap">
          <thead>
            <tr>
              <th>Categoria</th>
              @foreach($users as $user)
                <th style="color: {{ $user->id === $family->owner_id ? '#008cbd' : '#bc29c6' }};">
                  {{ $user->nickname }}
                </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach($data as $row)
              <tr>
            <td>
            @php
                $iconData = category_icons()[strtolower($row['category'])] ?? ['icon' => 'tag', 'color' => 'text-muted'];
            @endphp
            <i class="bi bi-{{ $iconData['icon'] }} {{ $iconData['color'] }} me-1"></i>
            {{ $row['category'] }}
            </td>
                @foreach($users as $user)
                  <td style="color: {{ $user->id === $family->owner_id ? '#008cbd' : '#bc29c6' }};">
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

  <!-- 🟪 DESTRA: Totali e differenze -->
  <div class="flex-fill">

    @php
      $ownerSum = collect($data)
        ->map(fn($row) => $row['values'][$family->owner->id] ?? 0)
        ->sum();

      $memberTotals = $family->members->mapWithKeys(function($member) use ($data) {
        $sum = collect($data)
          ->map(fn($row) => $row['values'][$member->id] ?? 0)
          ->sum();
        return [$member->id => $sum];
      });
    @endphp

    <!-- 🔷 SPESE TOTALI -->
    <div class="card card-totals">
      <div class="card-header">SPESE TOTALI</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table id="tot-table" class="table table-striped mb-0">
            <thead>
              <tr>
                <th>{{ $family->owner->nickname }}</th>
                @foreach($family->members as $member)
                  <th>{{ $member->nickname }}</th>
                @endforeach
                <th class="text-end">Totale</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><b>{{ number_format($ownerSum,0,',','.') }}€</b></td>
                @foreach($family->members as $member)
                  <td><b>{{ number_format($memberTotals[$member->id] ?? 0,0,',','.') }}€</b></td>
                @endforeach
                <td class="text-end">
                  <b>{{ number_format($ownerSum + $memberTotals->sum(), 0, ',', '.') }}€</b>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ⚖ DIFFERENZA SPESE -->
    <div class="card card-difference text-center">
      <div class="card-header justify-content-center">
        <i class="bi bi-balance-scale me-2"></i> DIFFERENZA SPESE
      </div>
      <div class="card-body p-3">
        @php
          $loggedUser = auth()->user();
          $otherUser  = $loggedUser->id === $family->owner->id ? $firstMember : $family->owner;
          $loggedIsOwner = $loggedUser->id === $family->owner->id;

          $userTotal  = $loggedIsOwner ? $allTimeOwnerSum + $credit : $allTimeMemberSum;
          $otherTotal = !$loggedIsOwner ? $allTimeOwnerSum + $credit : $allTimeMemberSum;

          $diff = $userTotal - $otherTotal;
          $amount = abs($diff);
          $formatted = number_format($amount, 2, ',', '.');
        @endphp

        @if ($diff === 0)
          <p class="fw-bold fs-5">
            🥳 Tutto bilanciato!<br>
            <strong style="color:#008cbd;">{{ $loggedUser->nickname }}</strong> e
            <strong style="color:#bc29c6;">{{ $otherUser->nickname }}</strong> sono pari! 🤝
          </p>
        @elseif ($diff > 0)
          <p class="fw-bold fs-5">
            🎉 Bene <strong style="color:#008cbd;">{{ $loggedUser->nickname }}</strong>!<br>
            Sei in credito verso <strong style="color:#bc29c6;">{{ $otherUser->nickname }}</strong> di<br>
            <span class="fs-3 text-success">{{ $formatted }}€</span>
          </p>
        @else
          <p class="fw-bold fs-5">
            😅 Uhm <strong style="color:#008cbd;">{{ $loggedUser->nickname }}</strong>...<br>
            Sei in debito con <strong style="color:#bc29c6;">{{ $otherUser->nickname }}</strong> di<br>
            <span class="fs-3 text-warning">{{ $formatted }}€</span>
          </p>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
