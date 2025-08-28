@extends('layouts.app')

@section('title', 'Fantacalcio - Quote')

@section('navbar-title')
  <span class="d-flex align-items-center gap-2">
    <i class="bi bi-cash-coin text-success"></i>
    <span class="fw-semibold">Quote</span>
  </span>
@endsection

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
  @if($errors->any())
    <div class="alert alert-warning">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-upload me-2"></i>Importa quotazioni da CSV</span>
      <small class="text-muted">Le importazioni sovrascrivono completamente la tabella</small>
    </div>
    <div class="card-body">
      <form action="{{ route('fantacalcio.quote.import') }}" method="POST" enctype="multipart/form-data" class="row g-3">
        @csrf
        <div class="col-12 col-md-8">
          <label for="csv" class="form-label">File CSV</label>
          <input type="file" class="form-control" id="csv" name="csv" accept=".csv,text/csv" required>
          <div class="form-text">
            Intestazioni richieste: <code>Id,R,RM,Nome,Squadra,FVM</code> — separatore virgola <em>o</em> punto e virgola.
          </div>
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-success w-100">
            <i class="bi bi-check2-circle me-1"></i> Importa
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
