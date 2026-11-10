@extends('layouts.base')

@section('body')
@php $isStaffLayout = in_array((current_user()['role'] ?? ''), ['admin', 'scanner'], true); @endphp

@if ($isStaffLayout)
    {{-- SSC panel: fixed sidebar on a desktop, a slide-in drawer on a phone --}}
    <div class="staff-shell">
        @include('partials.staff_sidebar')

        <div class="staff-main">
            @php
                $stUser    = current_user() ?? [];
                $stScanner = ($stUser['role'] ?? '') === 'scanner';
                $stDocs    = $stScanner ? 0 : waiting_documents();
                $stInq     = $stScanner ? 0 : \App\Models\SponsorInquiry::where('status', 'new')->count();
                $stTodo    = $stDocs + $stInq;
            @endphp
            <header class="staff-topbar no-print">
                <button type="button" class="ss-open" data-ss-open aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
                <div class="st-title flex-grow-1 min-w-0">
                    <div class="crumb">@yield('crumb', 'e-RESERVE')</div>
                    <h1 class="text-truncate">@yield('title')</h1>
                </div>

                <div class="st-tools">
                    <span class="st-chip d-none d-md-inline-flex"><i class="fa-regular fa-calendar"></i><span id="stDate">{{ now()->format('D, M j, Y') }}</span></span>
                    <span class="st-chip d-none d-lg-inline-flex"><i class="fa-regular fa-clock"></i><span id="stClock">{{ now()->format('g:i A') }}</span></span>

                    @unless ($stScanner)
                    <div class="dropdown">
                        <button class="st-bell" data-bs-toggle="dropdown" aria-expanded="false" aria-label="To do{{ $stTodo ? ' (' . $stTodo . ')' : '' }}">
                            <i class="fa-regular fa-bell"></i>@if ($stTodo)<span class="st-dot">{{ $stTodo > 9 ? '9+' : $stTodo }}</span>@endif
                        </button>
                        <div class="dropdown-menu dropdown-menu-end app-drop st-menu shadow border-0 mt-2">
                            <div class="st-menu-head">Needs your attention</div>
                            @foreach ([
                                ['fa-file-circle-check', 'Documents to review', $stDocs, route('admin.documents')],
                                ['fa-handshake', 'Sponsor inquiries', $stInq, route('admin.sponsors')],
                            ] as [$ic, $lb, $ct, $hr])
                                <a class="dropdown-item app-drop-item" href="{{ $hr }}"><i class="fa-solid {{ $ic }}"></i><span>{{ $lb }}</span><b class="st-count {{ $ct ? 'on' : '' }}">{{ $ct }}</b></a>
                            @endforeach
                        </div>
                    </div>
                    @endunless

                    <div class="dropdown">
                        <button class="st-user" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                            <span class="st-avatar">{{ $stUser['initials'] ?? 'SA' }}</span>
                            <span class="st-user-meta d-none d-md-flex">
                                <b class="text-truncate">{{ $stUser['name'] ?? 'Staff' }}</b>
                                <small>{{ $stScanner ? 'Gate scanner' : 'SSC staff' }}</small>
                            </span>
                            <i class="fa-solid fa-chevron-down st-caret d-none d-md-inline"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end app-drop st-menu shadow border-0 mt-2">
                            <li class="px-3 py-2">
                                <div class="fw-800 text-deep" style="font-size:.88rem;">{{ $stUser['name'] ?? 'Staff' }}</div>
                                <div class="text-muted2" style="font-size:.74rem;overflow-wrap:anywhere;">{{ $stUser['email'] ?? '' }}</div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('account.password') }}"><i class="fa-solid fa-key"></i><span>Change password</span></a></li>
                            @if (!empty($stUser['dual']))
                                <li><form method="post" action="{{ route('mode.switch', 'student') }}">@csrf
                                    <button type="submit" class="dropdown-item app-drop-item"><i class="fa-solid fa-repeat"></i><span>Student side</span></button>
                                </form></li>
                            @endif
                            <li><a class="dropdown-item app-drop-item" href="{{ route('home') }}"><i class="fa-solid fa-globe"></i><span>View public site</span></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('logout') }}">@csrf
                                    <button type="submit" class="dropdown-item app-drop-item text-danger"
                                            data-confirm-title="Sign out of e-RESERVE?" data-confirm-text="You can sign back in any time."
                                            data-confirm-icon="warning" data-confirm-yes="Sign out">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign out</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            <script>
                (function () {
                    var d = document.getElementById('stDate'), c = document.getElementById('stClock');
                    function tick() {
                        var n = new Date();
                        if (d) d.textContent = n.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                        if (c) c.textContent = n.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                    }
                    tick(); setInterval(tick, 20000);
                })();
            </script>

            <main class="app-content">
                <div class="app-container">
                    @yield('content')
                </div>
            </main>

            <footer class="app-foot no-print">
                <div class="app-container d-flex flex-wrap justify-content-between gap-2">
                    <span>&copy; {{ date('Y') }} Supreme Student Council · {{ ev('host') }}</span>
                    <span>{{ app_name() }} {{ config('ereserve.version') }} — <span class="text-gold fw-800">{{ config('ereserve.tagline') }}</span></span>
                </div>
            </footer>
        </div>
    </div>

    @include('partials.app_bottom_nav')
@else
    @include('partials.app_nav', ['activeNav' => trim($__env->yieldContent('nav'))])

    <div class="app-shell">

        <header class="page-head no-print">
            <div class="app-container d-flex align-items-center gap-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="crumb">@yield('crumb', 'e-RESERVE')</div>
                    <h1 class="text-truncate">@yield('title')</h1>
                </div>
                <span class="badge-soft badge-gold d-none d-md-inline-flex">
                    <i class="fa-solid fa-calendar-day"></i> {{ ev('date_text') }}
                </span>
            </div>
        </header>

        <main class="app-content">
            <div class="app-container">
                @yield('content')
            </div>
        </main>

        <footer class="app-foot no-print">
            <div class="app-container d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; {{ date('Y') }} Supreme Student Council · {{ ev('host') }}</span>
                <span>{{ app_name() }} {{ config('ereserve.version') }} — <span class="text-gold fw-800">{{ config('ereserve.tagline') }}</span></span>
            </div>
        </footer>
    </div>

    @include('partials.app_bottom_nav')
@endif
@endsection
