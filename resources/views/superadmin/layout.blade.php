@extends('layouts.base')

@section('body')
@php
    $me     = auth()->user();
    $active = trim($__env->yieldContent('nav'));
    $items  = [
        'Monitor'  => [['dashboard', 'superadmin.dashboard', 'fa-gauge-high', 'Dashboard'], ['performance', 'superadmin.performance', 'fa-chart-line', 'Performance']],
        'Accounts' => [['users', 'superadmin.users', 'fa-users', 'Users']],
        'Audit'    => [['activity', 'superadmin.activity', 'fa-list-check', 'Activity Log']],
    ];
@endphp
<link href="{{ asset_v('assets/css/superadmin.css') }}" rel="stylesheet">

<div class="sa-shell">
    <aside class="sa-side" id="saSide" aria-label="Super admin navigation">
        <div class="sa-brand">
            <img src="{{ asset(ssc('logo_small')) }}" alt="" width="44" height="44">
            <div>
                <b>e-<span>RESERVE</span></b>
                <small>System console</small>
                <em>SUPER ADMIN</em>
            </div>
        </div>

        <nav>
            @foreach ($items as $group => $links)
                <div class="sa-group">{{ $group }}</div>
                @foreach ($links as [$key, $route, $icon, $label])
                    <a href="{{ route($route) }}" class="sa-link {{ $active === $key ? 'is-on' : '' }}" @if ($active === $key) aria-current="page" @endif>
                        <i class="fa-solid {{ $icon }}"></i><span>{{ $label }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="sa-foot">
            <div class="sa-org"><img src="{{ asset(ssc('logo_small')) }}" alt="" width="28" height="28"><div><b>{{ ev('host') }}</b><small>{{ ev('name') }}</small></div></div>
            <form method="post" action="{{ route('logout') }}" class="sa-me">@csrf
                <span class="sa-avatar">{{ $me->initials ?: 'SA' }}</span>
                <div class="min-w-0"><b class="text-truncate d-block">{{ $me->name }}</b><small>Super Admin</small></div>
                <button type="submit" title="Sign out" aria-label="Sign out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button>
            </form>
        </div>
    </aside>
    <div class="sa-back" id="saBack"></div>

    <main class="sa-main">
        <header class="sa-head">
            <button type="button" class="sa-burger" id="saBurger" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
            <div class="sa-title">
                <h1>@yield('heading')</h1>
                <p>@yield('sub')</p>
            </div>
            <div class="sa-actions">@yield('actions')</div>
        </header>

        @yield('content')
    </main>
</div>
<script>
    (function () {
        var s = document.getElementById('saSide'), b = document.getElementById('saBack');
        function t(on) { s.classList.toggle('is-open', on); b.classList.toggle('is-open', on); }
        document.getElementById('saBurger').addEventListener('click', function () { t(true); });
        b.addEventListener('click', function () { t(false); });
    })();
</script>
@endsection
