{{-- Phone shortcut bar: the main pages only. The full menu is the hamburger in the top bar. --}}
@php
    $bnUser  = current_user() ?? [];
    $bnRole  = $bnUser['role'] ?? 'student';
    $bnActive = trim($__env->yieldContent('nav'));
    $bnItems = match ($bnRole) {
        'admin'   => [
            ['admin',         route('admin.dashboard'),     'fa-gauge-high',        'Home'],
            ['registrations', route('admin.registrations'), 'fa-users',             'Students'],
            ['documents',     route('admin.documents'),     'fa-file-circle-check', 'Review'],
            ['checkin',       route('admin.checkin'),       'fa-qrcode',            'Scan'],
        ],
        'scanner' => [
            ['checkin',       route('admin.checkin'),       'fa-qrcode',            'Gate Scan'],
        ],
        default   => [
            ['dashboard',     route('dashboard'),           'fa-house',             'Home'],
            ['documents',     route('documents.index'),     'fa-file-pdf',          'Documents'],
            ['ticket',        route('ticket'),              'fa-ticket',            'Ticket'],
            ['tracking',      route('tracking'),            'fa-route',             'Track'],
        ],
    };
@endphp
<nav class="bottom-nav no-print" aria-label="Quick menu">
    @foreach ($bnItems as [$key, $href, $icon, $label])
        <a href="{{ $href }}" class="bn-item {{ $bnActive === $key ? 'is-active' : '' }}" @if ($bnActive === $key) aria-current="page" @endif>
            <i class="fa-solid {{ $icon }}"></i><span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
