<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="@yield('viewport', 'width=device-width, initial-scale=1.0')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('desc', 'Register, upload your documents and get your ticket for ' . ev('name') . ' — ' . ev('host') . '.')">
    <meta name="theme-color" content="#2A8DB5">
    <title>@yield('title', 'Online Ticket Reservation') · {{ app_name() }}</title>

    <link rel="icon" type="image/png" href="{{ asset(ssc('logo_small')) }}">

    <!-- Everything is served from this site (no CDN round trips): fonts, Bootstrap, icons. -->
    <link rel="preload" href="{{ asset('vendor/fonts/plus-jakarta-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('vendor/fontawesome/webfonts/fa-solid-900.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link href="{{ asset_v('vendor/fonts/plus-jakarta-sans.css') }}" rel="stylesheet">
    <link href="{{ asset_v('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset_v('vendor/fontawesome/css/all.min.css') }}" rel="stylesheet">
    @hasSection('datatables')
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    @endif

    <!-- e-RESERVE design system (one bundled file) -->
    @foreach (asset_bundle_urls('css', ['style', 'modern', 'sky', 'glass', 'pages', 'sponsors', 'dash', 'student', 'staff', 'mobile']) as $cssUrl)
        <link href="{{ $cssUrl }}" rel="stylesheet">
    @endforeach
</head>
<body class="@yield('bodyClass')">

@hasSection('body')
    @yield('body')
@else
    @yield('content')
@endif

<script src="{{ asset_v('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset_v('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset_v('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
@hasSection('datatables')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
@endif

@foreach (asset_bundle_urls('js', ['app', 'fx']) as $jsUrl)
<script src="{{ $jsUrl }}"></script>
@endforeach

@if (!empty($flash))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: {!! json_encode($flash['type']) !!},
            title: {!! json_encode($flash['title']) !!},
            html: {!! json_encode($flash['text']) !!},
            confirmButtonText: 'Continue'
        });
    });
</script>
@endif

@stack('scripts')
</body>
</html>
