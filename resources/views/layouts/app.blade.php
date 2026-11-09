@extends('layouts.base')

@section('body')
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
@endsection
