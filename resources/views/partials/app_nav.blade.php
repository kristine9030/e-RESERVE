@php
    $user      = current_user() ?? [];
    $role      = $user['role'] ?? 'student';
    $activeNav = $activeNav ?? '';
    $isStudent = $role === 'student';
    $isScanner = $role === 'scanner';
    $progress  = $isStudent ? document_progress() : null;
    $docBadge  = $isStudent ? ($progress['complete'] ? '' : ($progress['blocked'] ? '!' : $progress['uploaded'] . '/' . $progress['total'])) : '';
    $waiting   = ($isStudent || $isScanner) ? 0 : waiting_documents();
    $catalog   = ($isStudent || $isScanner) ? [] : report_catalog();
    $notices   = $isStudent ? \App\Models\Notice::where('user_id', auth()->id())->orderByDesc('id')->limit(8)->get() : collect();
    $unread    = $isStudent ? \App\Models\Notice::where('user_id', auth()->id())->whereNull('read_at')->count() : 0;
    $noticeIcon = ['submitted' => 'fa-paper-plane', 'approved' => 'fa-circle-check', 'returned' => 'fa-rotate-left', 'ticket' => 'fa-ticket', 'waitlist' => 'fa-hourglass-half'];
@endphp
<nav class="navbar navbar-expand-xl app-nav" id="appNav">
    <div class="app-container">

        <a class="brand-lockup app-nav-brand" href="{{ $isStudent ? route('dashboard') : ($isScanner ? route('admin.checkin') : route('admin.dashboard')) }}">
            <img class="brand-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
            <span class="brand-text">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>

        @if ($isStudent)
            @include('partials.bell', ['bellClass' => 'd-xl-none ms-auto bell-mobile'])
        @endif

        <button class="navbar-toggler border-0 shadow-none {{ $isStudent ? '' : 'ms-auto' }}" type="button"
                data-bs-toggle="collapse" data-bs-target="#appNavMenu"
                aria-controls="appNavMenu" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="appNavMenu">
            <ul class="navbar-nav app-nav-list mx-xl-auto">
                @if ($isStudent)
                    @foreach ([
                        ['dashboard', route('dashboard'), 'Dashboard', ''],
                        ['documents', route('documents.index'), 'My Documents', $docBadge],
                        ['ticket', route('ticket'), 'My Ticket', ''],
                        ['tracking', route('tracking'), 'Track', ''],
                        ['profile', route('profile'), 'My Profile', ''],
                    ] as [$key, $href, $label, $badge])
                        <li class="nav-item"><a class="top-link {{ $activeNav === $key ? 'active' : '' }}" href="{{ $href }}">
                            <span>{{ $label }}</span>@if ($badge !== '')<span class="top-badge">{{ $badge }}</span>@endif
                        </a></li>
                    @endforeach
                    <li class="nav-item"><a class="top-link" href="{{ route('home') }}#guidelines"><span>Guidelines</span></a></li>
                @else
                    @foreach (array_filter([
                        ['admin', route('admin.dashboard'), 'Dashboard', ''],
                        ['registrations', route('admin.registrations'), 'Students', ''],
                        ['documents', route('admin.documents'), 'Review Documents', $waiting > 0 ? (string) $waiting : ''],
                        ['checkin', route('admin.checkin'), 'Gate Scan', ''],
                    ], fn ($i) => !$isScanner || $i[0] === 'checkin') as [$key, $href, $label, $badge])
                        <li class="nav-item"><a class="top-link {{ $activeNav === $key ? 'active' : '' }}" href="{{ $href }}">
                            <span>{{ $label }}</span>@if ($badge !== '')<span class="top-badge">{{ $badge }}</span>@endif
                        </a></li>
                    @endforeach

                    @unless ($isScanner)
                    <li class="nav-item dropdown">
                        <a class="top-link dropdown-toggle {{ in_array($activeNav, array_merge(['reports'], array_column($catalog, 'nav')), true) ? 'active' : '' }}"
                           href="#" id="navReports" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span>Reports</span></a>
                        <ul class="dropdown-menu app-drop" aria-labelledby="navReports">
                            <li><a class="dropdown-item app-drop-item {{ $activeNav === 'reports' ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                <i class="fa-solid fa-folder-open"></i><span>Reports Hub</span></a></li>
                            @foreach ($catalog as $meta)
                                <li><a class="dropdown-item app-drop-item {{ $activeNav === $meta['nav'] ? 'active' : '' }}" href="{{ $meta['page'] }}">
                                    <i class="fa-solid {{ $meta['icon'] }}"></i><span>{{ $meta['title'] }}</span></a></li>
                            @endforeach
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="top-link dropdown-toggle" href="#" id="navMore" role="button" data-bs-toggle="dropdown" aria-expanded="false"><span>More</span></a>
                        <ul class="dropdown-menu app-drop" aria-labelledby="navMore">
                            <?php $newInquiries = \App\Models\SponsorInquiry::where('status', 'new')->count(); ?>
                            <li><a class="dropdown-item app-drop-item {{ $activeNav === 'sponsors' ? 'active' : '' }}" href="{{ route('admin.sponsors') }}"><i class="fa-solid fa-handshake"></i><span>Sponsors{{ $newInquiries ? ' (' . $newInquiries . ' new)' : '' }}</span></a></li>
                            <li><a class="dropdown-item app-drop-item {{ $activeNav === 'team' ? 'active' : '' }}" href="{{ route('admin.team') }}"><i class="fa-solid fa-people-group"></i><span>SSC Team &amp; Accounts</span></a></li>
                            <li><a class="dropdown-item app-drop-item {{ $activeNav === 'settings' ? 'active' : '' }}" href="{{ route('admin.settings') }}"><i class="fa-solid fa-sliders"></i><span>Site Settings</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('dashboard') }}"><i class="fa-solid fa-user-graduate"></i><span>Student View</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('home') }}"><i class="fa-solid fa-house"></i><span>Event Page</span></a></li>
                        </ul>
                    </li>
                    @endunless
                @endif
            </ul>

            <div class="nav-account-m d-xl-none">
                <div class="nav-account-who">
                    <span class="avatar">{{ $user['initials'] ?? 'ST' }}</span>
                    <span class="nav-account-txt">
                        <strong>{{ $user['name'] ?? 'Guest' }}</strong>
                        <small>{{ $user['email'] ?? '' }}</small>
                    </span>
                </div>
                <a class="nav-account-btn" href="{{ route('account.password') }}"><i class="fa-solid fa-key"></i><span>Change Password</span></a>
                @if (!empty($user['dual']))
                    <form method="post" action="{{ route('mode.switch', $role === 'admin' ? 'student' : 'ssc') }}">@csrf
                        <button type="submit" class="nav-account-btn"><i class="fa-solid fa-repeat"></i><span>{{ $role === 'admin' ? 'Student side' : 'SSC panel' }}</span></button>
                    </form>
                @endif
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-account-btn danger"
                            data-confirm-title="Sign out of e-RESERVE?"
                            data-confirm-text="You can sign back in any time."
                            data-confirm-icon="warning"
                            data-confirm-yes="Sign out">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign Out</span>
                    </button>
                </form>
            </div>

            <div class="app-nav-tools">

                @if ($isStudent)
                    @include('partials.bell', ['bellClass' => 'd-none d-xl-block'])
                @endif

                <div class="dropdown d-none d-xl-block">
                    <button class="nav-user" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                        <span class="avatar">{{ $user['initials'] ?? 'ST' }}</span>
                        <span class="nav-user-meta d-none d-xxl-block">
                            <span class="u-name">{{ $user['name'] ?? 'Guest' }}</span>
                            <span class="u-role">{{ $role === 'admin' ? 'SSC Staff' : ($role === 'scanner' ? 'Gate Scanner' : 'Student') }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-down nav-user-caret d-none d-xxl-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end app-drop shadow border-0 mt-2" style="min-width:238px;">
                        <li class="px-3 py-2">
                            <div class="fw-800 text-deep" style="font-size:.9rem;">{{ $user['name'] ?? 'Guest' }}</div>
                            <div class="text-muted2" style="font-size:.76rem;">{{ $user['email'] ?? '—' }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @if ($isStudent)
                            <li><a class="dropdown-item app-drop-item" href="{{ route('profile') }}"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('documents.index') }}"><i class="fa-solid fa-file-pdf"></i><span>My Documents</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('ticket') }}"><i class="fa-solid fa-qrcode"></i><span>My Ticket</span></a></li>
                        @elseif ($isScanner)
                            <li><a class="dropdown-item app-drop-item" href="{{ route('admin.checkin') }}"><i class="fa-solid fa-qrcode"></i><span>Gate Scan</span></a></li>
                        @else
                            <li><a class="dropdown-item app-drop-item" href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-gauge-high"></i><span>Admin Dashboard</span></a></li>
                            <li><a class="dropdown-item app-drop-item" href="{{ route('reports.index') }}"><i class="fa-solid fa-folder-open"></i><span>Reports</span></a></li>
                        @endif
                        @if (!empty($user['dual']))
                            <li><form method="post" action="{{ route('mode.switch', $role === 'admin' ? 'student' : 'ssc') }}">@csrf
                                <button type="submit" class="dropdown-item app-drop-item"><i class="fa-solid fa-repeat"></i><span>{{ $role === 'admin' ? 'Student side' : 'SSC panel' }}</span></button>
                            </form></li>
                        @endif
                        <li><a class="dropdown-item app-drop-item" href="{{ route('account.password') }}"><i class="fa-solid fa-key"></i><span>Change Password</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item app-drop-item text-danger"
                                        data-confirm-title="Sign out of e-RESERVE?"
                                        data-confirm-text="You can sign back in any time."
                                        data-confirm-icon="warning"
                                        data-confirm-yes="Sign out">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sign Out</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
