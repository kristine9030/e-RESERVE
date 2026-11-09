@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('crumb', 'Administration')
@section('nav', 'admin')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-chart-line"></i> Live Overview</span>
            <h2>{{ ev('name') }}</h2>
            <p>{{ ev('date_text') }} · {{ ev('venue') }} · Capacity {{ number_format(ev('capacity')) }}</p>
        </div>
        <div class="col-lg-4">
            <div class="countdown-wrap" data-countdown="{{ ev('date_iso') }}">
                <div class="cd-box"><div class="cd-num" data-cd="days">00</div><div class="cd-label">Days</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="hours">00</div><div class="cd-label">Hrs</div></div>
                <div class="cd-box"><div class="cd-num" data-cd="minutes">00</div><div class="cd-label">Min</div></div>
            </div>
        </div>
    </div>
</div>

<!-- ================= Stat cards ================= -->
<div class="row g-3 g-lg-4 mb-4">
    <?php $cards = [
        ['fa-users',          'deep',   'Registered Students', $stats['students'],     'Accounts created'],
        ['fa-ticket',         'green',  'Tickets Issued',      $stats['tickets'],      'Valid or used'],
        ['fa-inbox',          'orange', 'Docs For Review',     $stats['docs_pending'], 'Waiting for staff'],
        ['fa-rotate-left',    'danger', 'Docs Returned',       $stats['docs_rejected'],'Waiting on students'],
        ['fa-user-check',     'info',   'Checked In',          $stats['checked_in'],   'Scanned at the gate'],
    ];
    foreach ($cards as $i => [$icon, $tone, $label, $value, $foot]): ?>
        <div class="col-6 col-lg-4 col-xl">
            <div class="stat-card fade-up d{{ $i + 1 }}">
                <div class="stat-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value" data-count-to="{{ $value }}">0</div>
                <div class="stat-foot">{{ $foot }}</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

    <!-- ================= Issuance trend ================= -->
    <div class="col-xl-8">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Tickets Issued</h5>
                    <span class="cell-sub">Per day · last 14 days with activity</span>
                </div>
                <a href="{{ route('reports.show', 'tickets') }}" class="btn btn-ghost btn-sm2">Report <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-body-p">
                @if ($trend)
                    <div class="mini-chart">
                        @foreach ($trend as $i => $t)
                            <div class="mc-col" data-bs-toggle="tooltip" title="{{ $t['label'] }}: {{ $t['count'] }} tickets">
                                <div class="mc-bar" style="height:{{ round($t['count'] / $maxTrend * 100) }}%;animation-delay:{{ $i * 45 }}ms;"></div>
                                <div class="mc-label">{{ $t['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="cell-sub mb-0 py-4 text-center">No tickets have been issued yet.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- ================= Capacity donut ================= -->
    <div class="col-xl-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Venue Capacity</h5></div>
            <div class="card-body-p">
                <?php $issuedPct = min(100, round($stats['tickets'] / ev('capacity') * 100, 1));
                $left      = max(0, ev('capacity') - $stats['tickets']); ?>
                <div class="donut" style="background:conic-gradient(
                        var(--green-700) 0% {{ $issuedPct }}%,
                        var(--green-050) {{ $issuedPct }}% 100%);">
                    <div class="donut-center">
                        <div class="n">{{ $issuedPct }}%</div>
                        <div class="l">Filled</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-700);"></span>
                        <span class="flex-grow-1">Tickets issued</span>
                        <strong>{{ number_format($stats['tickets']) }}</strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--green-050);"></span>
                        <span class="flex-grow-1">Seats available</span>
                        <strong>{{ number_format($left) }}</strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-swatch" style="background:var(--gold-500);"></span>
                        <span class="flex-grow-1">Still uploading</span>
                        <strong>{{ number_format($stats['incomplete']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ================= Review queue preview ================= -->
    <div class="col-xl-7">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Needs Review</h5>
                    <span class="cell-sub">{{ number_format($waiting) }} document{{ $waiting === 1 ? '' : 's' }} waiting</span>
                </div>
                <a href="{{ route('admin.documents') }}" class="btn btn-gold btn-sm2">Open queue <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="card-body-p">
                @if (!$queue)
                    <p class="cell-sub mb-0 py-3 text-center"><i class="fa-solid fa-circle-check text-green me-1"></i> Nothing waiting. Nice work.</p>
                @endif
                <?php foreach (array_slice($queue, 0, 6) as $d): $meta = config('ereserve.documents')[$d['doc_type']]; ?>
                    <div class="info-row">
                        <span class="info-icon"><i class="fa-solid {{ $meta['icon'] }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value" style="font-size:.88rem;">{{ $d['student_name'] }} · {{ $meta['short'] }}</div>
                            <div class="cell-sub">{{ $d['student_no'] }} · {{ pretty_date($d['uploaded_at'], 'M d · g:i A') }}</div>
                        </div>
                        <a href="{{ document_url((int) $d['id']) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-eye"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ================= Program breakdown ================= -->
    <div class="col-xl-5">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head">
                <h5 class="mb-0">Tickets by Program</h5>
                <a href="{{ route('reports.index') }}" class="text-gold fw-800" style="font-size:.76rem;">Reports <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body-p">
                <?php $maxProg = ($programs ? max(array_column($programs, 'total')) : 0) ?: 1;
                foreach ($programs as $p): ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:68%;">{{ $p['program'] }}</span>
                            <span class="v">{{ $p['ticketed'] }} / {{ $p['total'] }}</span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" data-width="{{ round($p['total'] / $maxProg * 100) }}" style="width:0;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                @if (!$programs)<p class="cell-sub mb-0">No students yet.</p>@endif
            </div>
        </div>
    </div>
</div>

<!-- ================= Latest registrations ================= -->
<div class="card-soft mb-4 fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Latest Registrations</h5>
            <span class="cell-sub">Newest accounts and how far along they are</span>
        </div>
        <a href="{{ route('admin.registrations') }}" class="btn btn-ghost btn-sm2">View all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-stack js-datatable" data-page-length="8" data-order-col="4" data-order-dir="desc" style="width:100%;">
            <thead>
                <tr><th>Student</th><th>Program</th><th>Documents</th><th>Ticket No.</th><th>Registered</th></tr>
            </thead>
            <tbody>
                @foreach (array_slice($regs, 0, 20) as $r)
                    <tr>
                        <td>
                            <div class="cell-strong">{{ $r['name'] }}</div>
                            <div class="cell-sub">{{ $r['student_no'] }}</div>
                        </td>
                        <td data-label="Program">{{ $r['program'] }}<div class="cell-sub">{{ $r['year'] }}</div></td>
                        <td data-label="Documents" data-order="{{ $r['doc_count'] }}">
                            <span class="badge-soft badge-{{ $r['doc_count'] === count(config('ereserve.documents')) ? 'success' : 'warning' }}">
                                {{ $r['doc_count'] }} / {{ count(config('ereserve.documents')) }} uploaded
                            </span>
                        </td>
                        <td data-label="Ticket No."><span class="mono">{{ $r['ticket_no'] ?: '—' }}</span></td>
                        <td class="cell-sub" data-label="Registered" data-order="{{ $r['registered_at'] }}">{{ pretty_date($r['registered_at'], 'M d, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Operations ================= -->
<div class="card-soft fade-up d5">
    <div class="card-head"><h5 class="mb-0">Operations</h5>
        <span class="badge-soft badge-gold"><i class="fa-solid fa-bolt"></i> Shortcuts</span>
    </div>
    <div class="card-body-p">
        <div class="row g-2">
            <?php $ops = [
                ['fa-file-circle-check', 'Review Documents', 'Approve or return uploaded PDFs',     route('admin.documents')],
                ['fa-users',             'Students',         'Every account and its document status', route('admin.registrations')],
                ['fa-qrcode',            'Gate Scan',        'Scan tickets at the entrance',         route('admin.checkin')],
                ['fa-folder-open',       'Reports Hub',      'Three official report forms',          route('reports.index')],
            ];
            foreach ($ops as [$icon, $title, $sub, $href]): ?>
                <div class="col-md-6 col-xl-3">
                    <a href="{{ $href }}" class="action-tile h-100">
                        <span class="t-icon"><i class="fa-solid {{ $icon }}"></i></span>
                        <span><span class="t-title d-block">{{ $title }}</span><span class="t-sub">{{ $sub }}</span></span>
                        <i class="fa-solid fa-arrow-right t-arrow"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
@endsection
