@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Area Amministrativa</h1>

    @if(auth()->user()->role === 'capofamiglia')
        {{-- Capofamiglia --}}
        <div class="mb-4">
            @if(!$ownFamily)
                <a href="{{ route('families.create') }}" class="btn btn-success">
                    Crea una nuova Famiglia
                </a>
            @else
                <p>Hai creato la famiglia “{{ $ownFamily->nickname }}”.</p>
            @endif
        </div>

        <h3>Richieste Pendenti</h3>
        @forelse($pendingRequests as $u)
            <div class="card mb-2 p-2">
                {{ $u->name }} {{ $u->surname }} ({{ $u->nickname }})
                <form action="{{ route('families.respond', [$ownFamily, $u]) }}"
                      method="POST" class="d-inline">
                    @csrf
                    <button name="action" value="accepted" class="btn btn-sm btn-primary">Accetta</button>
                    <button name="action" value="rejected" class="btn btn-sm btn-danger">Rifiuta</button>
                </form>
            </div>
        @empty
            <p>Nessuna richiesta in sospeso.</p>
        @endforelse

    @else
        {{-- Membro --}}
        <h3>Famiglie Disponibili</h3>
        @if(!$hasPending && !$inFamily)
            @foreach($families as $f)
                <div class="card mb-2 p-2">
                    <strong>{{ $f->nickname }}</strong>
                    ({{ $f->members_count }} membri)
                    <form action="{{ route('families.join', $f) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">Richiesta inviata</button>
                    </form>
                </div>
            @endforeach
        @elseif($hasPending)
            <p>Hai già una richiesta in sospeso.</p>
        @else
            <p>Sei già membro della famiglia “{{ auth()->user()->families()->wherePivot('status','accepted')->first()->nickname }}”.</p>
        @endif
    @endif
</div>
@endsection
