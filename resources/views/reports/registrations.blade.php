@extends('layouts.app')
@section('title', 'Registration Report')
@section('crumb', 'Reports')
@section('nav', 'report_registrations')

@section('content')
<div class="page-hero fade-up no-print">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <span class="eyebrow on-dark"><i class="fa-solid fa-clipboard-list"></i> Report 01 · {{ $ds['code'] }}</span>
            <h2>Registration Report</h2>
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
        $tone = [0 => 'deep', 1 => 'green', 2 => 'orange', 3 => 'gold'][$i] ?? 'deep';
        $icon = [0 => 'fa-users', 1 => 'fa-circle-check', 2 => 'fa-hourglass-half', 3 => 'fa-chart-simple'][$i] ?? 'fa-users'; ?>
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
    <div class="col-lg-7">
        <div class="card-soft h-100 fade-up d2">
            <div class="card-head"><h5 class="mb-0">Documents by Status</h5></div>
            <div class="card-body-p">
                <?php foreach ($byDoc as $d):
                    $total = max(1, array_sum([$d['approved'], $d['submitted'], $d['rejected'], $d['missing']])); ?>
                    <div class="prog-row">
                        <div class="prog-top">
                            <span class="n">{{ $d['label'] }}</span>
                            <span class="v">{{ $d['approved'] + $d['submitted'] }} / {{ $total }} uploaded</span>
                        </div>
                        <div class="prog-track">
                            <div class="prog-fill" data-width="{{ round(($d['approved'] + $d['submitted']) / $total * 100) }}" style="width:0;"></div>
                        </div>
                        <div class="cell-sub mt-1">
                            {{ $d['approved'] }} approved · {{ $d['submitted'] }} for review ·
                            {{ $d['rejected'] }} returned · {{ $d['missing'] }} missing
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft h-100 fade-up d3">
            <div class="card-head"><h5 class="mb-0">Registrations by Year Level</h5></div>
            <div class="card-body-p">
                @foreach ($byYear as $year => $n)
                    <div class="receipt-row">
                        <span class="k">{{ $year }}</span>
                        <span class="v">{{ number_format($n) }} student{{ $n === 1 ? '' : 's' }}</span>
                    </div>
                @endforeach
                @if (!$byYear)
                    <p class="cell-sub mb-0">No students have registered yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@include('partials.report_paper')
@endsection
