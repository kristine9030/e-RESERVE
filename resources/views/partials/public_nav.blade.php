@php $onLanding = request()->routeIs('home'); @endphp
<nav class="navbar navbar-expand-lg nav-public">
    <div class="container">
        <a class="brand-lockup" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
            <span class="brand-text">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#publicNav" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars text-deep fs-5"></i>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="{{ $onLanding ? '#home' : route('home') . '#home' }}">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $onLanding ? '#about' : route('home') . '#about' }}">About SIGLA</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ $onLanding ? '#guidelines' : route('home') . '#guidelines' }}">Guidelines</a></li>
                <li class="nav-item nav-extra"><a class="nav-link" href="{{ $onLanding ? '#lineup' : route('home') . '#lineup' }}">Lineup</a></li>
                <li class="nav-item nav-extra"><a class="nav-link" href="{{ $onLanding ? '#faq' : route('home') . '#faq' }}">FAQ</a></li>
                <li class="nav-item nav-extra"><a class="nav-link" href="{{ $onLanding ? '#contact' : route('home') . '#contact' }}">Contact</a></li>
                {{-- Phones: the first four links stay, everything else lives under "More". --}}
                <li class="nav-item dropdown nav-more">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">More</a>
                    <ul class="dropdown-menu dropdown-menu-end nav-more-menu">
                        <li><a class="dropdown-item" href="{{ $onLanding ? '#lineup' : route('home') . '#lineup' }}">Lineup</a></li>
                        <li><a class="dropdown-item" href="{{ $onLanding ? '#faq' : route('home') . '#faq' }}">FAQ</a></li>
                        <li><a class="dropdown-item" href="{{ $onLanding ? '#contact' : route('home') . '#contact' }}">Contact</a></li>
                        <li><hr class="dropdown-divider"></li>
                        @if (is_logged_in())
                            <li><a class="dropdown-item fw-bold" href="{{ is_admin() ? route('admin.dashboard') : route('dashboard') }}"><i class="fa-solid fa-gauge-high me-2"></i>My Dashboard</a></li>
                        @else
                            <li><a class="dropdown-item" href="{{ route('login') }}"><i class="fa-solid fa-right-to-bracket me-2"></i>Sign In</a></li>
                            <li><a class="dropdown-item fw-bold nav-more-cta" href="{{ route('register') }}"><i class="fa-solid fa-ticket me-2"></i>Get my ticket</a></li>
                        @endif
                    </ul>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0 nav-actions">
                @if (is_logged_in())
                    <a href="{{ is_admin() ? route('admin.dashboard') : route('dashboard') }}" class="btn btn-outline-green btn-sm2">
                        <i class="fa-solid fa-gauge-high"></i> My Dashboard
                    </a>
                    <form method="post" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm2" aria-label="Sign out"
                                data-confirm-title="Sign out of e-RESERVE?"
                                data-confirm-text="You can sign back in any time."
                                data-confirm-icon="warning"
                                data-confirm-yes="Sign out">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm2">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-gold btn-sm2">
                        Get my ticket <i class="fa-solid fa-arrow-right"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
</nav>
