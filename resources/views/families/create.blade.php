@extends('layouts.app')
@section('content')
  <h1>Crea una nuova famiglia</h1>
  <form action="{{ route('families.store') }}" method="POST">
    @csrf
    <div>
      <label>Nickname famiglia</label>
      <input type="text" name="nickname" required>
    </div>
    <button>Crea</button>
  </form>
@endsection
