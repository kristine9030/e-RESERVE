<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="@yield('viewport', 'width=device-width, initial-scale=1.0')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('desc', 'Register, upload your documents and get your ticket for ' . ev('name') . ' — ' . ev('host') . '.')">
    <meta name="theme-color" content="#2A8DB5">
    <title>@yield('title', 'Online Ticket Reservation') · {{ app_name() }}</title>

    <link rel="icon" type="image/png" href="{{ asset(ssc('logo')) }}">

    <!-- Fonts: Plus Jakarta Sans (UI) + Montserrat (legacy fallback) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300..800&family=Montserrat:ital,wght@0,300..900;1,400..800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- e-RESERVE design system -->
    @foreach (['style', 'modern', 'sky', 'glass', 'pages', 'sponsors', 'dash', 'student', 'mobile'] as $css)
        <link href="{{ asset('assets/css/' . $css . '.css') }}" rel="stylesheet">
    @endforeach
</head>
<body class="@yield('bodyClass')">

@hasSection('body')
    @yield('body')
@else
    @yield('content')
@endif

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/js/fx.js') }}"></script>

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
