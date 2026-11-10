@php $cols = $ds['columns']; $span = count($cols); @endphp
{{-- Landscape A4. The 0.5 in margin is made inside the page (padding + spacer rows), so it holds even when the
     print dialog is set to "Margins: None"; @page margin 0 also keeps the browser's URL/date line off the paper. --}}
<style>@page { size: A4 landscape; margin: 0; }</style>
<div class="report-sheet fade-up d3">

    <header class="report-head">
        <div class="report-seal"><img src="{{ asset(ssc('logo')) }}" alt=""></div>
        <div class="report-org">{{ ev('host') }}</div>
        <div class="report-org">{{ ev('org') }}</div>
        <h2 class="report-title">{{ $ds['title'] }}</h2>
        <div class="report-sub">{{ $ds['subtitle'] }}</div>
        <div class="report-event">{{ ev('name') }} · {{ ev('date_text') }} · {{ ev('venue') }}</div>
        <div class="report-meta">
            <span>Report Code <strong>{{ $ds['code'] }}</strong></span>
            <span>Generated {{ date('F d, Y · g:i A') }}</span>
            <span>{{ count($ds['rows']) }} record{{ count($ds['rows']) === 1 ? '' : 's' }}</span>
        </div>
    </header>

    <div class="report-filter no-print">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" class="form-control" placeholder="Filter records…" data-report-filter>
        <span class="cell-sub" data-report-count>{{ count($ds['rows']) }} of {{ count($ds['rows']) }}</span>
    </div>

    <div class="report-scroll">
        <table class="report-table">
            @php $sumW = max(1, array_sum(array_column($cols, 'width'))); @endphp
            <colgroup>@foreach ($cols as $c)<col style="width:{{ round($c['width'] / $sumW * 100, 2) }}%">@endforeach</colgroup>
            <thead>
                <tr class="pg-pad"><td colspan="{{ $span }}"></td></tr>
                <tr>
                    @foreach ($cols as $c)
                        <th class="{{ !empty($c['nowrap']) ? 'nw' : '' }}"
                            style="text-align:{{ $rpt->align($c['type']) }};">{{ $c['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tfoot><tr class="pg-pad"><td colspan="{{ $span }}"></td></tr></tfoot>
            <tbody>
                @foreach ($ds['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $value)
                            <td class="{{ !empty($cols[$i]['nowrap']) ? 'nw' : '' }} {{ $cols[$i]['type'] === 'date' ? 'dt' : '' }}"
                                style="text-align:{{ $rpt->align($cols[$i]['type']) }};">
                                {{ $rpt->format($cols[$i]['type'], $value) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if (!$ds['rows'])
                    <tr><td colspan="{{ $span }}" class="report-empty">No records for this period.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <section class="report-summary">
        <h6>Summary</h6>
        <div class="report-summary-grid">
            @foreach ($ds['summary'] as [$label, $value, $type])
                <div class="report-summary-row">
                    <span class="k">{{ $label }}</span>
                    <span class="v">{{ $rpt->format($type, $value) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="report-sign">
        @foreach ($ds['signatories'] as [$role, $who])
            <div class="report-sign-col">
                <span class="role">{{ $role }}:</span>
                <span class="rule"></span>
                <span class="who">{{ $who }}</span>
            </div>
        @endforeach
    </footer>

    <div class="print-doc-foot">
        {{ $ds['title'] }} · {{ $ds['code'] }} ·
        {{ count($ds['rows']) }} record{{ count($ds['rows']) === 1 ? '' : 's' }} ·
        generated {{ date('F d, Y · g:i A') }} by {{ app_name() }} {{ config('ereserve.version') }}<br>
        {{ ev('org') }} · {{ ev('host') }}
    </div>
</div>
