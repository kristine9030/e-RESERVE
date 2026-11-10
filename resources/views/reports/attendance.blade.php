@extends('layouts.app')
@section('title', 'Attendance Report')
@section('crumb', 'Reports')
@section('nav', 'report_attendance')

@section('content')
<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-check"></i> Report 03 · {{ $ds['code'] }}</span>
            <h2>Attendance Report</h2>
            <p>{{ $ds['subtitle'] }}.</p>
        </div>
        <div class="col-lg-5">
            @include('partials.report_actions')
        </div>
    </div>
</div>

@include('reports._filter')

<div class="row g-3 mb-4 no-print">
    <?php foreach ($ds['summary'] as $i => [$label, $value, $type]):
        $tone = [0 => 'gold', 1 => 'green', 2 => 'danger', 3 => 'info'][$i] ?? 'deep';
        $icon = [0 => 'fa-ticket', 1 => 'fa-user-check', 2 => 'fa-user-xmark', 3 => 'fa-chart-simple'][$i] ?? 'fa-ticket'; ?>
        <div class="col-6 col-lg-3">
            <div class="stat-card fade-up d{{ $i + 1 }}">
                <div class="stat-icon {{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value" style="font-size:1.35rem;">{{ $rpt->format($type, $value) }}</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4 no-print">
    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Check-in by Gate</h5></div>
            <div class="card-body-p">
                <?php foreach ($byGate as $gate => $g):
                    $pct = $g['expected'] ? round($g['present'] / $g['expected'] * 100) : 0; ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n">{{ $gate }}</span>
                            <span class="v">{{ $g['present'] }} / {{ $g['expected'] }}</span>
                        </div>
                        <div class="prog-track"><div class="prog-fill" data-width="{{ $pct }}" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                @if (!$byGate)
                    <p class="cell-sub mb-0">No gates assigned yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Check-in by Program</h5></div>
            <div class="card-body-p">
                <?php foreach ($byProgram as $prog => $p):
                    $pct = $p['expected'] ? round($p['present'] / $p['expected'] * 100) : 0; ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n text-truncate" style="max-width:68%;">{{ $prog }}</span>
                            <span class="v">{{ $p['present'] }} / {{ $p['expected'] }}</span>
                        </div>
                        <div class="prog-track"><div class="prog-fill gold" data-width="{{ $pct }}" style="width:0;"></div></div>
                    </div>
                <?php endforeach; ?>
                @if (!$byProgram)
                    <p class="cell-sub mb-0">No check-ins recorded yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft h-100 fade-up d4">
            <div class="card-head"><h5 class="mb-0">Arrival Curve</h5></div>
            <div class="card-body-p">
                <?php $maxHour = ($byHour ? max($byHour) : 0) ?: 1; ?>
                @foreach ($byHour as $hour => $n)
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n">{{ date('g:00 A', strtotime($hour . ':00')) }}</span>
                            <span class="v">{{ $n }} scanned</span>
                        </div>
                        <div class="prog-track"><div class="prog-fill" data-width="{{ round($n / $maxHour * 100) }}" style="width:0;"></div></div>
                    </div>
                @endforeach
                @if (!$byHour)
                    <p class="cell-sub mb-0">The gate has not opened yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@include('partials.report_paper')
@endsection
