@extends('layouts.app')
@section('title', 'Reports Hub')
@section('crumb', 'Reports')
@section('nav', 'reports')

@section('content')
<div class="page-hero fade-up no-print">
    <span class="eyebrow on-dark"><i class="fa-solid fa-folder-open"></i> Documentation</span>
    <h2>Reports</h2>
    <p>Official report forms. Export to a spreadsheet or print the signed paper form.</p>
</div>

<div class="row g-4 mb-4 no-print">
    <?php $i = 0; foreach ($catalog as $key => $meta): $i++; ?>
        <div class="col-lg-4">
            <div class="card-soft feature-card h-100 d-flex flex-column fade-up d{{ $i }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="feature-icon"><i class="fa-solid {{ $meta['icon'] }}"></i></div>
                    <span class="badge-soft badge-gold">{{ $meta['code'] }}</span>
                </div>

                <h5>{{ $meta['title'] }}</h5>
                <p>{{ $meta['blurb'] }}</p>

                <div class="cell-sub mb-3">{{ number_format($counts[$key]) }} record{{ $counts[$key] === 1 ? '' : 's' }}</div>

                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <a href="{{ $meta['page'] }}" class="btn btn-outline-green btn-sm2">
                        <i class="fa-solid fa-eye"></i> Open
                    </a>
                    <a href="{{ route('reports.export', ['report' => $key, 'format' => 'sheet']) }}" class="btn btn-gold btn-sm2">
                        <i class="fa-solid fa-file-excel"></i> Sheet
                    </a>
                    <a href="{{ route('reports.export', ['report' => $key, 'format' => 'pdf']) }}" class="btn btn-ghost btn-sm2">
                        <i class="fa-solid fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('reports.export', ['report' => $key, 'format' => 'csv']) }}" class="btn btn-ghost btn-sm2">
                        <i class="fa-solid fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-soft fade-up d4">
    <div class="card-head">
        <h5 class="mb-0">Event Snapshot</h5>
        <span class="cell-sub">Generated {{ date('F d, Y · g:i A') }}</span>
    </div>
    <div class="card-body-p">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="receipt-row"><span class="k">Event</span><span class="v">{{ ev('name') }}</span></div>
                <div class="receipt-row"><span class="k">Date &amp; Venue</span><span class="v">{{ ev('date_text') }} · {{ ev('venue') }}</span></div>
                <div class="receipt-row"><span class="k">Organiser</span><span class="v">{{ ev('org') }}</span></div>
                <div class="receipt-row"><span class="k">Capacity</span><span class="v">{{ number_format(ev('capacity')) }} students</span></div>
            </div>
            <div class="col-md-6">
                <div class="receipt-row"><span class="k">Registered Students</span><span class="v">{{ number_format($stats['students']) }}</span></div>
                <div class="receipt-row"><span class="k">Tickets Issued</span><span class="v">{{ number_format($stats['tickets']) }}</span></div>
                <div class="receipt-row"><span class="k">Documents For Review</span><span class="v">{{ number_format($stats['docs_pending']) }}</span></div>
                <div class="receipt-row"><span class="k">Documents Returned</span><span class="v">{{ number_format($stats['docs_rejected']) }}</span></div>
                <div class="receipt-row"><span class="k">Checked-in Students</span><span class="v">{{ number_format($stats['checked_in']) }}</span></div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4 no-print">
            <button class="btn btn-outline-green" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Snapshot</button>
        </div>
    </div>
</div>
@endsection
