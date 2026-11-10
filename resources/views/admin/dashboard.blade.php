@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('crumb', 'Administration')
@section('nav', 'admin')
@section('datatables', '1')

@section('content')
<div class="page-hero page-hero-compact fade-up">
    <div class="row align-items-center g-3">
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

<?php
    $cap       = max(1, (int) ev('capacity'));
    $issuedPct = min(100, round($stats['tickets'] / $cap * 100, 1));
    $seatsLeft = max(0, $cap - $stats['tickets']);
    $inPct     = $stats['tickets'] > 0 ? min(100, round($stats['checked_in'] / $stats['tickets'] * 100)) : 0;
    $holdPct   = $stats['students'] > 0 ? min(100, round($stats['tickets'] / $stats['students'] * 100)) : 0;
    $kpis = [
        ['fa-users',      'k-blue',   'Registered accounts', $stats['students'],   number_format($stats['incomplete']) . ' still uploading', null],
        ['fa-ticket',     'k-green',  'Tickets issued', $stats['tickets'],    $issuedPct . '% of ' . number_format($cap) . ' seats', $issuedPct],
        ['fa-user-check', 'k-orange', 'Checked in at gate', $stats['checked_in'], $inPct . '% of ticket holders', $inPct],
        ['fa-chair',      'k-gold',   'Seats left',     $seatsLeft,           $holdPct . '% of registered hold a ticket', 100 - $issuedPct],
    ];
    $topCollege = $colleges[0] ?? null;
    $maxCol     = max(1, (int) ($topCollege['students'] ?? 0));

    // Document status donut
    $dA = $stats['docs_approved']; $dW = $stats['docs_pending']; $dR = $stats['docs_rejected'];
    $dSum = max(1, $dA + $dW + $dR);
    $a1 = round($dA / $dSum * 100, 1); $a2 = round(($dA + $dW) / $dSum * 100, 1);
?>
<div class="row g-3 g-xl-4 mb-4 dash-top">
    <div class="col-xl-8">

        <!-- ================= 4 key numbers ================= -->
        <div class="row g-3 mb-3" data-live="kpis">
            @foreach ($kpis as $i => [$icon, $tone, $label, $value, $foot, $bar])
                <div class="col-6 col-md-3">
                    <div class="kpi {{ $tone }} fade-up d{{ $i + 1 }}">
                        <span class="kpi-icon"><i class="fa-solid {{ $icon }}"></i></span>
                        <div class="kpi-label">{{ $label }}</div>
                        <div class="kpi-value" data-count-to="{{ $value }}">0</div>
                        <div class="kpi-foot">{{ $foot }}</div>
                        @if ($bar !== null)<div class="kpi-bar"><i style="width:{{ max(3, $bar) }}%"></i></div>@endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- ================= Two donuts ================= -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card-soft h-100 fade-up d3">
                    <div class="card-head"><h5 class="mb-0">Seats filled</h5></div>
                    <div class="card-body-p donut-row">
                        <div class="donut donut-sm" style="background:conic-gradient(#2A8DB5 0% {{ $issuedPct }}%, #E3F0F6 {{ $issuedPct }}% 100%);">
                            <div class="donut-center"><div class="n">{{ $issuedPct }}%</div><div class="l">Filled</div></div>
                        </div>
                        <div class="donut-legend">
                            <div class="legend-item"><span class="legend-swatch" style="background:#2A8DB5"></span><span class="flex-grow-1">Issued</span><strong>{{ number_format($stats['tickets']) }}</strong></div>
                            <div class="legend-item"><span class="legend-swatch" style="background:#E3F0F6"></span><span class="flex-grow-1">Available</span><strong>{{ number_format($seatsLeft) }}</strong></div>
                            <div class="legend-item"><span class="legend-swatch" style="background:#F9A05C"></span><span class="flex-grow-1">Voided</span><strong>{{ number_format($stats['tickets_void']) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-soft h-100 fade-up d4">
                    <div class="card-head"><h5 class="mb-0">Document status</h5></div>
                    <div class="card-body-p donut-row">
                        <div class="donut donut-sm" style="background:conic-gradient(#1E9A5A 0% {{ $a1 }}%, #F58634 {{ $a1 }}% {{ $a2 }}%, #E5484D {{ $a2 }}% 100%);">
                            <div class="donut-center"><div class="n">{{ number_format($dA + $dW + $dR) }}</div><div class="l">Files</div></div>
                        </div>
                        <div class="donut-legend">
                            <div class="legend-item"><span class="legend-swatch" style="background:#1E9A5A"></span><span class="flex-grow-1">Approved</span><strong>{{ number_format($dA) }}</strong></div>
                            <div class="legend-item"><span class="legend-swatch" style="background:#F58634"></span><span class="flex-grow-1">Waiting</span><strong>{{ number_format($dW) }}</strong></div>
                            <div class="legend-item"><span class="legend-swatch" style="background:#E5484D"></span><span class="flex-grow-1">Returned</span><strong>{{ number_format($dR) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Students / faculty / sponsors ================= -->
        <div class="card-soft mt-3 fade-up d4">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Registrations by group</h5>
                    <span class="cell-sub">Students, instructors &amp; staff, sponsors</span>
                </div>
            </div>
            <div class="card-body-p">
                <div class="aud-grid">
                    @foreach ([
                        ['student',  'fa-user-graduate', 'a-blue',   'Students'],
                        ['employee', 'fa-briefcase',     'a-orange', 'Instructors & staff'],
                        ['sponsor',  'fa-handshake',     'a-gold',   'Sponsors'],
                    ] as [$ak, $aIcon, $aTone, $aLabel])
                        <?php $a = $audience[$ak]; ?>
                        <div class="aud {{ $aTone }}">
                            <button type="button" class="aud-main" data-bs-toggle="modal" data-bs-target="#grpModal-{{ $ak }}">
                                <div class="aud-head"><span class="aud-icon"><i class="fa-solid {{ $aIcon }}"></i></span><span class="aud-name">{{ $aLabel }}</span><i class="fa-solid fa-chevron-right aud-go"></i></div>
                                <div class="aud-num">{{ number_format($a['registered']) }}<small>accounts</small></div>
                            </button>
                            <div class="aud-stats">
                                <a href="{{ route('admin.registrations', ['type' => $ak, 'ticket' => 'held']) }}"><b>{{ number_format($a['tickets']) }}</b><span>Tickets</span></a>
                                <a href="{{ route('admin.registrations', ['type' => $ak, 'ticket' => 'used']) }}"><b>{{ number_format($a['checked_in']) }}</b><span>Checked in</span></a>
                                <a class="{{ $a['waiting'] ? 'is-hot' : '' }}" href="{{ route('admin.registrations', ['type' => $ak, 'docs' => 'pending']) }}"><b>{{ number_format($a['waiting']) }}</b><span>For review</span></a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ================= Colleges: who has the most ================= -->
    <div class="col-xl-4">
        <div class="card-soft college-rank h-100 fade-up d2">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Students by college</h5>
                    <span class="cell-sub">Ranked by registered students</span>
                </div>
                @if ($topCollege && $topCollege['students'] > 0)
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-trophy"></i> {{ $topCollege['code'] ?: '—' }}</span>
                @endif
            </div>
            <div class="card-body-p">
                @forelse ($colleges as $i => $c)
                    <?php $isCol = $c['code'] !== ''; ?>
                    @if ($isCol)
                    <button type="button" class="cr-row {{ $i === 0 && $c['students'] > 0 ? 'is-top' : '' }}" data-bs-toggle="modal" data-bs-target="#colModal-{{ $c['code'] }}">
                    @else
                    <a href="{{ route('admin.registrations') }}" class="cr-row">
                    @endif
                        <span class="cr-rank">{{ $i + 1 }}</span>
                        <div class="cr-main">
                            <div class="cr-top">
                                <span class="cr-code">{{ $c['code'] ?: 'No college' }}</span>
                                <span class="cr-n">{{ number_format($c['students']) }}<small> · {{ $c['share'] }}%</small></span>
                            </div>
                            <div class="cr-track"><div class="cr-fill" data-width="{{ round($c['students'] / $maxCol * 100) }}" style="width:0;"></div></div>
                            <div class="cr-sub">{{ number_format($c['tickets']) }} ticket{{ $c['tickets'] === 1 ? '' : 's' }} · {{ $c['done'] }}% fully approved @if ($c['docs_waiting'] > 0)· <b class="cr-wait">{{ $c['docs_waiting'] }} to review</b>@endif</div>
                        </div>
                        <i class="fa-solid fa-chevron-right cr-go"></i>
                    @if ($isCol)</button>@else</a>@endif
                @empty
                    <p class="cell-sub mb-0 py-3 text-center">No students yet.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>

<!-- ================= College → programs (opened from "Students by college") ================= -->
@foreach ($colleges as $c)
    @continue($c['code'] === '')
    <?php $pb = $programsBy[$c['code']] ?? ['students' => 0, 'tickets' => 0, 'programs' => []]; ?>
    <div class="modal fade" id="colModal-{{ $c['code'] }}" tabindex="-1" aria-labelledby="colModalTitle-{{ $c['code'] }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content col-modal">
                <div class="modal-header">
                    <div class="min-w-0">
                        <h5 class="modal-title" id="colModalTitle-{{ $c['code'] }}">{{ $c['code'] }}</h5>
                        <div class="cell-sub">{{ $c['name'] }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cm-summary">
                        <div><b>{{ number_format($pb['students']) }}</b><span>Students</span></div>
                        <div><b>{{ number_format($pb['tickets']) }}</b><span>Tickets</span></div>
                        <div><b>{{ count($pb['programs']) }}</b><span>Programs</span></div>
                    </div>
                    @foreach ($pb['programs'] as $pg)
                        <a class="cm-row {{ $pg['students'] ? 'is-on' : '' }}" href="{{ route('admin.registrations', ['college' => $c['code'], 'program' => $pg['program']]) }}">
                            <span class="cm-name">{{ $pg['program'] }}</span>
                            <span class="cm-n"><b>{{ $pg['students'] }}</b> student{{ $pg['students'] === 1 ? '' : 's' }}@if ($pg['tickets']) · {{ $pg['tickets'] }} ticket{{ $pg['tickets'] === 1 ? '' : 's' }}@endif</span>
                            <span class="cm-go">Roster <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <a class="btn btn-ghost btn-sm2" href="{{ route('admin.documents', ['college' => $c['code']]) }}"><i class="fa-solid fa-file-circle-check"></i> Review documents</a>
                    <a class="btn btn-gold btn-sm2" href="{{ route('admin.registrations', ['college' => $c['code']]) }}">Full {{ $c['code'] }} roster <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
@endforeach

<!-- ================= Group → details (opened from "Registrations by group") ================= -->
<div class="modal fade" id="grpModal-student" tabindex="-1" aria-labelledby="grpTitle-student" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content col-modal">
            <div class="modal-header">
                <div class="min-w-0"><h5 class="modal-title" id="grpTitle-student">Students</h5><div class="cell-sub">Every program offered, by college</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="cm-summary">
                    <div><b>{{ number_format($audience['student']['registered']) }}</b><span>Students</span></div>
                    <div><b>{{ number_format($audience['student']['tickets']) }}</b><span>Tickets</span></div>
                    <div><b>{{ collect($programsBy)->sum(fn ($c) => count($c['programs'])) }}</b><span>Programs</span></div>
                </div>
                @foreach ($programsBy as $code => $pb)
                    <div class="cm-group">{{ $code }} <small>· {{ config('ereserve.colleges')[$code] ?? '' }}</small></div>
                    @foreach ($pb['programs'] as $pg)
                        <a class="cm-row {{ $pg['students'] ? 'is-on' : '' }}" href="{{ route('admin.registrations', ['college' => $code, 'program' => $pg['program']]) }}">
                            <span class="cm-name">{{ $pg['program'] }}</span>
                            <span class="cm-n"><b>{{ $pg['students'] }}</b> student{{ $pg['students'] === 1 ? '' : 's' }}@if ($pg['tickets']) · {{ $pg['tickets'] }} ticket{{ $pg['tickets'] === 1 ? '' : 's' }}@endif</span>
                            <span class="cm-go">Roster <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    @endforeach
                @endforeach
            </div>
            <div class="modal-footer">
                <a class="btn btn-gold btn-sm2" href="{{ route('admin.registrations', ['type' => 'student']) }}">All students <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="grpModal-employee" tabindex="-1" aria-labelledby="grpTitle-employee" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content col-modal">
            <div class="modal-header">
                <div class="min-w-0"><h5 class="modal-title" id="grpTitle-employee">Instructors &amp; staff</h5><div class="cell-sub">Faculty, instructors and staff accounts</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="cm-summary">
                    <div><b>{{ number_format($audience['employee']['registered']) }}</b><span>Accounts</span></div>
                    <div><b>{{ number_format($audience['employee']['tickets']) }}</b><span>Tickets</span></div>
                    <div><b>{{ number_format($audience['employee']['waiting']) }}</b><span>For review</span></div>
                </div>
                @foreach ($groupDetail['employee'] as $et)
                    <a class="cm-row {{ $et['accounts'] ? 'is-on' : '' }}" href="{{ route('admin.registrations', ['type' => $et['key']]) }}">
                        <span class="cm-name">{{ $et['label'] }}</span>
                        <span class="cm-n"><b>{{ $et['accounts'] }}</b> account{{ $et['accounts'] === 1 ? '' : 's' }}@if ($et['tickets']) · {{ $et['tickets'] }} ticket{{ $et['tickets'] === 1 ? '' : 's' }}@endif</span>
                        <span class="cm-go">Roster <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                @endforeach
            </div>
            <div class="modal-footer">
                <a class="btn btn-gold btn-sm2" href="{{ route('admin.registrations', ['type' => 'employee']) }}">All instructors &amp; staff <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="grpModal-sponsor" tabindex="-1" aria-labelledby="grpTitle-sponsor" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content col-modal">
            <div class="modal-header">
                <div class="min-w-0"><h5 class="modal-title" id="grpTitle-sponsor">Sponsors</h5><div class="cell-sub">Sponsor and partner accounts</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="cm-summary">
                    <div><b>{{ number_format($audience['sponsor']['registered']) }}</b><span>Accounts</span></div>
                    <div><b>{{ number_format($audience['sponsor']['tickets']) }}</b><span>Tickets</span></div>
                    <div><b>{{ number_format($audience['sponsor']['waiting']) }}</b><span>For review</span></div>
                </div>
                @forelse ($groupDetail['sponsors'] as $sp)
                    <a class="cm-row is-on" href="{{ route('admin.registrations', ['type' => 'sponsor', 'q' => $sp['email']]) }}">
                        <span class="cm-name">{{ $sp['name'] }}</span>
                        <span class="cm-n">{{ $sp['email'] }} · {{ $sp['ticket'] ? 'Has a ticket' : 'No ticket yet' }}</span>
                        <span class="cm-go">Record <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                @empty
                    <p class="cell-sub text-center py-3 mb-0">No sponsor accounts yet.</p>
                @endforelse
            </div>
            <div class="modal-footer">
                <a class="btn btn-gold btn-sm2" href="{{ route('admin.registrations', ['type' => 'sponsor']) }}">All sponsors <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- ================= Document review queue ================= -->
<div class="row g-3 g-xl-4 mb-4">
    <!-- ================= Review queue preview ================= -->
    <div class="col-12">
        <div class="card-soft h-100 fade-up d3" data-live="queue">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">Document review queue</h5>
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
</div>

<!-- ================= Latest registrations ================= -->
<div class="card-soft mb-4 fade-up d4">
    <div class="card-head">
        <div>
            <h5 class="mb-0">Latest registrations</h5>
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
                            <span class="badge-soft badge-{{ $r['doc_count'] === $r['doc_total'] ? 'success' : 'warning' }}">
                                {{ $r['doc_count'] }} / {{ $r['doc_total'] }} uploaded
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
@endsection
