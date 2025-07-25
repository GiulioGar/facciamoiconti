@extends('layouts.app')
@section('content')
  <h1>Famiglie</h1>
  @foreach($families as $f)
    <div>
      <strong>{{ $f->nickname }}</strong>
      ({{ $f->members_count }} membri)
      @if(auth()->user()->role == 'membro')
        <form action="{{ route('families.join', $f) }}" method="POST">
          @csrf
          <button>Richiedi di entrare</button>
        </form>
      @endif
    </div>
  @endforeach
@endsection
