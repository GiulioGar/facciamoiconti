<nav class="navbar navbar-expand-lg navbar-light bg-light rounded mb-4 shadow-sm">
  <div class="container-fluid">
    <!-- Brand -->
    <a class="navbar-brand fw-bold text-primary" href="{{ route('fantacalcio.index') }}">
      <i class="bi bi-trophy-fill me-1"></i> Stagione 2025/2026
    </a>

    <!-- Toggle per mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#fantacalcioNav" aria-controls="fantacalcioNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Links -->
    <div class="collapse navbar-collapse" id="fantacalcioNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('fantacalcio.index') ? 'active fw-semibold' : '' }}"
             href="{{ route('fantacalcio.index') }}">
            <i class="bi bi-house-heart me-1"></i> Home
          </a>
        </li>
        <li class="nav-item">
  <a class="nav-link {{ request()->routeIs('fantacalcio.rosa') ? 'active fw-semibold' : '' }}"
     href="{{ route('fantacalcio.rosa') }}">
    <i class="bi bi-clipboard2-check me-1"></i> Rosa
  </a>
</li>
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('fantacalcio.quote') ? 'active fw-semibold' : '' }}"
             href="{{ route('fantacalcio.quote') }}">
            <i class="bi bi-cash-coin me-1"></i> Quote
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
