@auth
@php
$user = auth()->user();

$ownFamilies = $ownFamilies ?? collect();
$famName = null;
if ($user->role === 'capofamiglia' && $ownFamilies->isNotEmpty()) {
    $famName = $ownFamilies->first()->nickname;
} elseif ($user->families()->wherePivot('status', 'accepted')->exists()) {
    $famName = $user->families()->wherePivot('status', 'accepted')->first()->nickname;
}
    $pendingCount = isset($pendingRequests) ? $pendingRequests->count() : 0;
@endphp

<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>

  <!-- Messaggio a sinistra -->
  <div class="navbar-nav flex-row align-items-center me-auto">
    <h5 class="mb-0 text-primary d-none d-md-block">
      <i class="bx bx-time-five me-2"></i> È ora di fare i conti...!
    </h5>
  </div>

  <!-- Lato destro -->
  <div class="navbar-nav flex-row align-items-center ms-auto">

    <!-- Username -->
    <li class="nav-item d-flex align-items-center me-3">
      <i class="bx bx-user-circle fs-4 me-1 text-primary"></i>
      <span class="fw-medium">{{ $user->name }}</span>
    </li>

    <!-- Famiglia -->
    @if ($famName)
    <li class="nav-item d-flex align-items-center me-3">
      <i class="bx bx-home-smile fs-4 me-1 text-success"></i>
      <span class="fw-medium">{{ $famName }}</span>
    </li>
    @endif

    <!-- Richieste pendenti -->
    @if ($pendingCount > 0)
    <li class="nav-item me-3">
      <span class="badge bg-danger rounded-pill">{{ $pendingCount }}</span>
    </li>
    @endif

    <!-- Dropdown utente -->
    <li class="nav-item dropdown-user dropdown">
      <a class="nav-link dropdown-toggle hide-arrow p-0" href="#" data-bs-toggle="dropdown">
        <div class="avatar avatar-online">
          <img src="{{ asset('assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle" />
        </div>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">

        <!-- Profilo utente -->
        <li>
          <div class="dropdown-item">
            <div class="d-flex">
              <div class="flex-shrink-0 me-3">
                <div class="avatar avatar-online">
                  <img src="{{ asset('assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle" />
                </div>
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-0">{{ $user->name }}</h6>
                <small class="text-muted">{{ ucfirst($user->role) }}</small>
              </div>
            </div>
          </div>
        </li>

        <li><div class="dropdown-divider my-1"></div></li>

        <!-- Voci disabilitate -->
        <li>
          <a class="dropdown-item" href="javascript:void(0);" onclick="return false;">
            <i class="bx bx-user me-2"></i>
            <span>Profilo</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="javascript:void(0);" onclick="return false;">
            <i class="bx bx-cog me-2"></i>
            <span>Impostazioni</span>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="javascript:void(0);" onclick="return false;">
            <i class="bx bx-credit-card me-2"></i>
            <span>Abbonamento</span>
          </a>
        </li>

        <li><div class="dropdown-divider my-1"></div></li>

        <!-- Logout -->
        <li>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item">
              <i class="bx bx-power-off me-2"></i>
              <span>Logout</span>
            </button>
          </form>
        </li>

      </ul>
    </li>
  </div>
</nav>
@endauth
