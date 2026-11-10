@php
    $ssUser      = current_user() ?? [];
    $ssRole      = $ssUser['role'] ?? 'admin';
    $ssScanner   = $ssRole === 'scanner';
    $ssActive    = trim($__env->yieldContent('nav'));
    $ssWaiting   = $ssScanner ? 0 : waiting_documents();
    $ssCatalog   = $ssScanner ? [] : report_catalog();
    $ssInquiries = $ssScanner ? 0 : \App\Models\SponsorInquiry::where('status', 'new')->count();
    $ssReportKeys = array_merge(['reports'], array_column($ssCatalog, 'nav'));
    $ssInReports = in_array($ssActive, $ssReportKeys, true);

    $ssOnRecords = $ssActive === 'registrations';
    $ssRecType   = request()->routeIs('admin.registrations') ? (string) (request('type') ?: 'all') : null;
    if ($ssRecType !== null && array_key_exists($ssRecType, config('ereserve.employee_types'))) { $ssRecType = 'employee'; }
    $ssCounts    = $ssScanner ? [] : app(\App\Services\Stats::class)->groupCounts();
    $ssRecords   = [
        ['all',      'All records',         'fa-layer-group',   array_sum($ssCounts)],
        ['student',  'Students',            'fa-user-graduate', $ssCounts['student'] ?? 0],
        ['employee', 'Instructors & staff', 'fa-briefcase',     $ssCounts['employee'] ?? 0],
        ['sponsor',  'Sponsors',            'fa-handshake',     $ssCounts['sponsor'] ?? 0],
    ];

    $ssLink = function (string $key, string $href, string $icon, string $label, $badge = 0) use ($ssActive) {
        $on = $ssActive === $key;
        return '<a href="' . e($href) . '" title="' . e($label) . '" class="ss-link' . ($on ? ' is-active' : '') . '"' . ($on ? ' aria-current="page"' : '') . '>'
             . '<i class="fa-solid ' . e($icon) . '"></i><span class="ss-text">' . e($label) . '</span>'
             . ((int) $badge > 0 ? '<span class="ss-badge">' . (int) $badge . '</span>' : '')
             . '</a>';
    };
@endphp
<script>try { if (localStorage.getItem('ssMini') === '1') document.body.classList.add('ss-mini'); } catch (e) {}</script>
<aside class="staff-sidebar" id="staffSidebar" aria-label="SSC navigation">
    <div class="ss-brand">
        <a class="ss-brand-link" href="{{ $ssScanner ? route('admin.checkin') : route('admin.dashboard') }}">
            <img class="ss-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
            <span class="ss-brand-text">
                <span class="ss-brand-name">e-<span>RESERVE</span></span>
                <span class="ss-brand-sub">{{ $ssScanner ? 'Gate scanner' : 'SSC panel' }}</span>
            </span>
        </a>
        <button type="button" class="ss-close" id="ssClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <nav class="ss-nav">
        <div class="ss-menu-label">Menu</div>
        @if ($ssScanner)
            <div class="ss-group">
                <div class="ss-group-title">Event night</div>
                {!! $ssLink('checkin', route('admin.checkin'), 'fa-qrcode', 'Gate Scan') !!}
            </div>
        @else
            <div class="ss-group">
                <div class="ss-group-title">Overview</div>
                {!! $ssLink('admin', route('admin.dashboard'), 'fa-gauge-high', 'Dashboard') !!}
            </div>

            <div class="ss-group">
                <div class="ss-group-title">People &amp; documents</div>
                <a href="#ssRecords" title="Records" class="ss-link ss-toggle {{ $ssOnRecords ? 'is-active-parent' : '' }}" data-bs-toggle="collapse" role="button"
                   aria-expanded="{{ $ssOnRecords ? 'true' : 'false' }}" aria-controls="ssRecords">
                    <i class="fa-solid fa-address-book"></i><span class="ss-text">Records</span><i class="fa-solid fa-chevron-down ss-caret"></i>
                </a>
                <div class="collapse {{ $ssOnRecords ? 'show' : '' }}" id="ssRecords">
                    <div class="ss-sub">
                        @foreach ($ssRecords as [$rk, $rLabel, $rIcon, $rCount])
                            <a href="{{ route('admin.registrations', ['type' => $rk]) }}" title="{{ $rLabel }}" class="ss-link ss-sublink {{ $ssRecType === $rk ? 'is-active' : '' }}">
                                <span class="ss-text">{{ $rLabel }}</span><span class="ss-count">{{ number_format($rCount) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                {!! $ssLink('documents', route('admin.documents'), 'fa-file-circle-check', 'Review Documents', $ssWaiting) !!}
            </div>

            <div class="ss-group">
                <div class="ss-group-title">Event night</div>
                {!! $ssLink('checkin', route('admin.checkin'), 'fa-qrcode', 'Gate Scan') !!}
            </div>

            <div class="ss-group">
                <div class="ss-group-title">Reports</div>
                <a href="#ssReports" title="Reports" class="ss-link ss-toggle {{ $ssInReports ? 'is-active-parent' : '' }}" data-bs-toggle="collapse" role="button"
                   aria-expanded="{{ $ssInReports ? 'true' : 'false' }}" aria-controls="ssReports">
                    <i class="fa-solid fa-folder-open"></i><span class="ss-text">Reports</span><i class="fa-solid fa-chevron-down ss-caret"></i>
                </a>
                <div class="collapse {{ $ssInReports ? 'show' : '' }}" id="ssReports">
                    <div class="ss-sub">
                        <a href="{{ route('reports.index') }}" class="ss-link ss-sublink {{ $ssActive === 'reports' ? 'is-active' : '' }}"><span class="ss-text">Reports Hub</span></a>
                        @foreach ($ssCatalog as $meta)
                            <a href="{{ $meta['page'] }}" class="ss-link ss-sublink {{ $ssActive === $meta['nav'] ? 'is-active' : '' }}"><span class="ss-text">{{ $meta['title'] }}</span></a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ss-group">
                <div class="ss-group-title">Community &amp; team</div>
                {!! $ssLink('sponsors', route('admin.sponsors'), 'fa-handshake', 'Sponsors', $ssInquiries) !!}
                {!! $ssLink('team', route('admin.team'), 'fa-people-group', 'SSC Team & Accounts') !!}
                {!! $ssLink('settings', route('admin.settings'), 'fa-sliders', 'Site Settings') !!}
            </div>
        @endif
    </nav>

    <div class="ss-foot">
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" title="Sign out" class="ss-signout"
                    data-confirm-title="Sign out of e-RESERVE?"
                    data-confirm-text="You can sign back in any time."
                    data-confirm-icon="warning"
                    data-confirm-yes="Sign out">
                <i class="fa-solid fa-arrow-right-from-bracket"></i><span class="ss-text">Sign out</span>
            </button>
        </form>
    </div>
</aside>
<button type="button" class="ss-fold" data-ss-fold aria-label="Collapse or expand the sidebar" aria-expanded="true" title="Collapse / expand sidebar"><i class="fa-solid fa-chevron-left"></i></button>
<div class="staff-backdrop" id="staffBackdrop"></div>
<script>
    (function () {
        var side = document.getElementById('staffSidebar'), back = document.getElementById('staffBackdrop');
        if (!side) return;
        function open()  { side.classList.add('is-open'); back.classList.add('is-open'); document.body.classList.add('ss-lock'); }
        function close() { side.classList.remove('is-open'); back.classList.remove('is-open'); document.body.classList.remove('ss-lock'); }
        function fold(on) {
            document.body.classList.toggle('ss-mini', on); syncTips();
            try { localStorage.setItem('ssMini', on ? '1' : '0'); } catch (e) {}
            var b = document.querySelector('[data-ss-fold]'); if (b) b.setAttribute('aria-expanded', on ? 'false' : 'true');
        }
        /* Tooltips only help in the icon rail; expanded, they just cover the next menu item. */
        var tipped = [].slice.call(side.querySelectorAll('a[title], button[title]'));
        tipped.forEach(function (el) { el.dataset.tip = el.getAttribute('title'); });
        function syncTips() {
            var mini = document.body.classList.contains('ss-mini');
            tipped.forEach(function (el) { if (mini) el.setAttribute('title', el.dataset.tip); else el.removeAttribute('title'); });
        }
        syncTips();
        requestAnimationFrame(function () { requestAnimationFrame(function () { document.body.classList.add('ss-ready'); }); });
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-ss-fold]')) { e.preventDefault(); fold(!document.body.classList.contains('ss-mini')); return; }
            if (document.body.classList.contains('ss-mini') && window.innerWidth >= 992 && e.target.closest('#staffSidebar .ss-toggle')) { e.preventDefault(); e.stopPropagation(); fold(false); return; }
            if (e.target.closest('[data-ss-open]')) { e.preventDefault(); open(); }
            else if (e.target.closest('#ssClose') || e.target === back) { close(); }
            else if (e.target.closest('#staffSidebar a.ss-link:not(.ss-toggle)')) { close(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
</script>
