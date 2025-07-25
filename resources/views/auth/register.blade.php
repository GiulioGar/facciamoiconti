@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">{{ __('Register') }}</div>
        <div class="card-body">
          <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- Nome --}}
            <div class="row mb-3">
              <label for="name" class="col-md-4 col-form-label text-md-end">{{ __('Name') }}</label>
              <div class="col-md-6">
                <input id="name" type="text"
                  class="form-control @error('name') is-invalid @enderror"
                  name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                @error('name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Cognome --}}
            <div class="row mb-3">
              <label for="surname" class="col-md-4 col-form-label text-md-end">Cognome</label>
              <div class="col-md-6">
                <input id="surname" type="text"
                  class="form-control @error('surname') is-invalid @enderror"
                  name="surname" value="{{ old('surname') }}" required>
                @error('surname')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Email --}}
            <div class="row mb-3">
              <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>
              <div class="col-md-6">
                <input id="email" type="email"
                  class="form-control @error('email') is-invalid @enderror"
                  name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Nickname --}}
            <div class="row mb-3">
              <label for="nickname" class="col-md-4 col-form-label text-md-end">Nickname</label>
              <div class="col-md-6">
                <input id="nickname" type="text"
                  class="form-control @error('nickname') is-invalid @enderror"
                  name="nickname" value="{{ old('nickname') }}" required>
                @error('nickname')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Ruolo --}}
            <div class="row mb-3">
              <label for="role" class="col-md-4 col-form-label text-md-end">Ruolo</label>
              <div class="col-md-6">
                <select id="role" name="role"
                  class="form-control @error('role') is-invalid @enderror" required>
                  <option value="">Seleziona ruolo</option>
                  <option value="capofamiglia" {{ old('role')=='capofamiglia'?'selected':'' }}>
                    Capofamiglia
                  </option>
                  <option value="membro" {{ old('role')=='membro'?'selected':'' }}>
                    Membro della famiglia
                  </option>
                </select>
                @error('role')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Password --}}
            <div class="row mb-3">
              <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>
              <div class="col-md-6">
                <input id="password" type="password"
                  class="form-control @error('password') is-invalid @enderror"
                  name="password" required autocomplete="new-password">
                @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
              </div>
            </div>

            {{-- Conferma Password --}}
            <div class="row mb-3">
              <label for="password-confirm"
                class="col-md-4 col-form-label text-md-end">{{ __('Confirm Password') }}</label>
              <div class="col-md-6">
                <input id="password-confirm" type="password"
                  class="form-control" name="password_confirmation" required autocomplete="new-password">
              </div>
            </div>

            {{-- Submit --}}
            <div class="row mb-0">
              <div class="col-md-6 offset-md-4">
                <button type="submit" class="btn btn-primary">
                  {{ __('Register') }}
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
