<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $ds['title'] }}</title>
<style>
    @page { margin: 0.5in; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #2B373D; }
    .center { text-align: center; }
    .seal { width: 46px; height: 46px; }
    .org { font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; color: #6B7F8C; line-height: 1.6; }
    h1 { font-size: 18px; margin: 5px 0 2px; color: #173B4D; }
    .sub { font-size: 8.5px; color: #46555E; }
    .event { font-size: 8.5px; font-weight: bold; color: #B87A18; margin-top: 3px; }
    .meta { font-size: 7px; color: #8697A1; margin-top: 6px; }
    .meta b { color: #1D5772; }
    .rule { border-bottom: 1.4px solid #1D5772; margin: 8px 0 10px; }
    table.t { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .t th { background: #1D5772; color: #fff; text-align: left; padding: 5px 5px; font-size: 6.4px; letter-spacing: .5px; text-transform: uppercase; border: .6px solid #2A7FA6; }
    .t td { padding: 4px 5px; border: .6px solid #D5E3EB; vertical-align: top; word-wrap: break-word; }
    .t tr.z td { background: #F4F9FC; }
    .r { text-align: right; }
    .empty { text-align: center; color: #8697A1; padding: 16px; }
    .sum-h { margin-top: 12px; border-top: 1.4px solid #1D5772; padding-top: 7px; font-size: 7px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; color: #1D5772; }
    table.sum { width: 100%; border-collapse: separate; border-spacing: 8px 4px; margin-left: -8px; }
    .sum td { background: #EEF8FC; padding: 5px 8px; width: 25%; }
    .sum .k { font-weight: bold; color: #1D5772; font-size: 7.4px; }
    .sum .v { font-weight: bold; color: #173B4D; font-size: 9px; text-align: right; }
    table.sign { width: 100%; border-collapse: separate; border-spacing: 14px 0; margin: 6px 0 0 -14px; }
    .sign td { width: 33.33%; vertical-align: bottom; }
    .sign .role { font-size: 7.4px; color: #46555E; height: 34px; }
    .sign .who { border-top: .8px solid #2B373D; padding-top: 3px; font-size: 8px; font-weight: bold; }
    .foot { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 6.5px; color: #6B7F8C; text-align: center; }
</style>
</head>
<body>
@php
    $cols  = $ds['columns'];
    $total = max(1, array_sum(array_column($cols, 'width')));
    $n     = count($ds['rows']);
@endphp
<div class="center">
    @if ($logo)<img class="seal" src="{{ $logo }}" alt=""><br>@endif
    <div class="org">{{ ev('host') }}<br>{{ ev('org') }}</div>
    <h1>{{ $ds['title'] }}</h1>
    <div class="sub">{{ $ds['subtitle'] }}</div>
    <div class="event">{{ ev('name') }} · {{ ev('date_text') }} · {{ ev('venue') }}</div>
    <div class="meta">Report Code <b>{{ $ds['code'] }}</b> &nbsp;·&nbsp; Generated {{ date('F d, Y · g:i A') }} &nbsp;·&nbsp; {{ $n }} record{{ $n === 1 ? '' : 's' }}</div>
</div>
<div class="rule"></div>

<table class="t">
    <colgroup>@foreach ($cols as $c)<col style="width:{{ round($c['width'] / $total * 100, 2) }}%">@endforeach</colgroup>
    <thead>
        <tr>@foreach ($cols as $c)<th class="{{ $c['type'] === 'number' ? 'r' : '' }}">{{ $c['label'] }}</th>@endforeach</tr>
    </thead>
    <tbody>
        @forelse ($ds['rows'] as $k => $row)
            <tr class="{{ $k % 2 ? 'z' : '' }}">
                @foreach ($row as $i => $v)
                    <td class="{{ $cols[$i]['type'] === 'number' ? 'r' : '' }}">{{ $rpt->format($cols[$i]['type'], $v) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($cols) }}" class="empty">No records for this period.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="sum-h">Summary</div>
<table class="sum">
    @foreach (array_chunk($ds['summary'], 4) as $chunk)
        <tr>
            @foreach ($chunk as [$label, $value, $type])
                <td><table style="width:100%;"><tr><td class="k" style="background:none;padding:0;width:60%;">{{ $label }}</td><td class="v" style="background:none;padding:0;">{{ $rpt->format($type, $value) }}</td></tr></table></td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="sign">
    <tr>@foreach ($ds['signatories'] as [$role, $who])<td class="role">{{ $role }}:</td>@endforeach</tr>
    <tr>@foreach ($ds['signatories'] as [$role, $who])<td class="who">{{ $who }}</td>@endforeach</tr>
</table>

<div class="foot">{{ $ds['title'] }} · {{ $ds['code'] }} · {{ $n }} record{{ $n === 1 ? '' : 's' }} · generated {{ date('F d, Y · g:i A') }} by {{ app_name() }} {{ config('ereserve.version') }}<br>{{ ev('org') }} · {{ ev('host') }}</div>
</body>
</html>
