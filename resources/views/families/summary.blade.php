@extends('layouts.app')

@section('title', 'Budget Familiare')

@section('navbar-title')
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-people-fill fs-5 text-primary"></i>
    <span class="fw-semibold">Budget Familiare</span>
  </span>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  @php
    function euro($v) { return number_format((float)$v, 2, ',', '.').' €'; }
  @endphp

  @if(!$family)
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <div>Nessuna famiglia associata al tuo account. Aggiungine una per vedere il riepilogo.</div>
    </div>
  @else

    {{-- Mega Card: Patrimonio Familiare (Totale di tutti i budget) --}}
    <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg,#008cbd1a,#bc29c61a);">
      <div class="card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
        <div>
          <div class="text-muted mb-1">Famiglia</div>
          <h4 class="mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-house-heart-fill"></i>
            {{ $family->nickname ?? '—' }}
          </h4>
          <div class="text-muted">Patrimonio Familiare (somma di tutti i budget)</div>
        </div>
        <div class="text-end mt-3 mt-md-0">
          <div class="text-muted small">Totale</div>
          <h2 class="mb-0 fw-bold">{{ euro($totale) }}</h2>
        </div>
      </div>
    </div>

    {{-- 4 Card: Familiare / Extra / Risparmi / Personale --}}
    <div class="row g-3">
      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold">Budget Spese familiari</span>
              <span class="badge bg-info">Familiare</span>
            </div>
            <h4 class="mb-1">{{ euro($familiare) }}</h4>
            <div class="text-muted small">Totale allocato alla categoria Familiare</div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold">Budget Auto e Vacanze</span>
              <span class="badge bg-warning text-dark">Extra</span>
            </div>
            <h4 class="mb-1">{{ euro($extra) }}</h4>
            <div class="text-muted small">Totale allocato alla categoria Extra</div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold">Risparmi</span>
              <span class="badge bg-success">Risparmi</span>
            </div>
            <h4 class="mb-1">{{ euro($risparmi) }}</h4>
            <div class="text-muted small">Totale allocato alla categoria Risparmi</div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold">Budget Spese Personale</span>
              <span class="badge bg-primary">Personale</span>
            </div>
            <h4 class="mb-1">{{ euro($personale) }}</h4>
            <div class="text-muted small">Totale allocato alla categoria Personale</div>
          </div>
        </div>
      </div>
    </div>

  @endif
</div>
@endsection
