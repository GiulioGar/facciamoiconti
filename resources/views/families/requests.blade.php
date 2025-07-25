@extends('layouts.app')
@section('content')
  <h1>Richieste per {{ $family->nickname }}</h1>
  @foreach($pending as $u)
    <div>
      {{ $u->name }} {{ $u->surname }} ({{ $u->nickname }})
      <form action="{{ route('families.respond', [$family, $u]) }}" method="POST" style="display:inline">
        @csrf
        <button name="action" value="accepted">Accetta</button>
        <button name="action" value="rejected">Rifiuta</button>
      </form>
    </div>
  @endforeach
@endsection
