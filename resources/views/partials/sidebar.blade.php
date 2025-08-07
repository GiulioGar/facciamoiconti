<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
          <div class="app-brand demo">
            <a href="{{ route('home') }}" class="app-brand-link">
   <span class="app-brand-logo demo">
        <img src="{{ asset('assets/img/avatars/polli.png') }}" alt="Logo" style="height: 70px; border-radius: 50%;">
        </span>
              <span class="app-brand-text demo menu-text fw-bold ms-2">Conti</span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i> 
            </a>
          </div>

          <div class="menu-divider mt-0"></div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <!-- Dashboards -->
            <li class="menu-item active open">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-home-smile"></i>
                <div class="text-truncate" data-i18n="Dashboards">Dashboards</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('home') ? 'active' : '' }}">
                <a href="{{ route('home') }}" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-home"></i>
                    <div class="text-truncate" data-i18n="Homepage">Homepage</div>
                </a>
                </li>
                <li class="menu-item {{ request()->routeIs('incomes.*') ? 'active' : '' }}">
                <a href="{{ route('incomes.index') }}" class="menu-link">
                    {{-- se vuoi un’icona, Sneat usa ad esempio: --}}
                    <i class="menu-icon tf-icons bx bx-wallet"></i>
                    <div class="text-truncate" data-i18n="Entrate">Entrate</div>
                </a>
                </li>

                <li class="menu-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <a href="{{ route('expenses.index') }}" class="menu-link">
                    {{-- se vuoi un’icona, Sneat usa ad esempio: --}}
                    <i class="menu-icon tf-icons bx  bx-wallet-alt"></i>
                    <div class="text-truncate" data-i18n="Uscite">Uscite</div>
                </a>
                </li>

              </ul>
            </li>

<!-- FAMIGLIA -->
@auth
  @php
      $user = auth()->user();
      $family = $user->families->first() ?: $user->ownedFamilies->first();
  @endphp

  @if($family)
    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">CONTI FAMILIARI</span>
    </li>
    <li class="menu-item {{ request()->routeIs('families.combined-balances') ? 'active' : '' }}">
      <a href="{{ route('families.combined-balances', ['family' => $family->id]) }}" class="menu-link">
        <i class="menu-icon tf-icons bx bxs-group"></i>
        <div class="text-truncate" data-i18n="Conti uniti">Spese Comuni</div>
      </a>
    </li>
  @endif
@endauth



            <!-- Misc -->
            <li class="menu-header small text-uppercase"><span class="menu-header-text">IMPOSTAZIONI</span></li>
            <li class="menu-item">
              <a
                href="{{ route('admin.dashboard') }}"
                target="_blank"
                class="menu-link">
                <i class="menu-icon tf-icons bx bx-support"></i>
                <div class="text-truncate" data-i18n="Support">Area Admin</div>
              </a>
            </li>
          </ul>
        </aside>
        <!-- / Menu -->
