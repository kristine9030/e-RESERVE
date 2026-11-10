<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $d['title'] }}</title>
<style>
    @page { margin: 24px 28px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.4px; color: #173B4D; }
    .head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .head td { vertical-align: middle; padding: 0; }
    .logo { width: 34px; height: 34px; }
    h1 { font-size: 15px; margin: 0 0 2px; color: #173B4D; }
    .sub { font-size: 8px; color: #5F7684; }
    .meta { text-align: right; font-size: 7.6px; color: #5F7684; line-height: 1.5; }
    .bar { height: 3px; background: #1F6E94; margin: 4px 0 8px; }
    table.t { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .t th { background: #1F6E94; color: #fff; text-align: left; padding: 5px 5px; font-size: 7px; letter-spacing: .4px; text-transform: uppercase; }
    .t td { padding: 4px 5px; border-bottom: .5px solid #E1ECF2; vertical-align: top; word-wrap: break-word; }
    .t tr:nth-child(even) td { background: #F6FAFC; }
    .name { font-weight: bold; }
    .muted { color: #5F7684; }
    .foot { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #7C8F88; text-align: center; }
</style>
</head>
<body>
@php
    // PDF columns: name, group, id, email, college, program, documents, ticket no., ticket, checked in, registered
    $pick = [0, 1, 2, 3, 4, 5, 7, 8, 9, 10, 12];
    $w    = [11, 7, 7, 14, 6, 15, 17, 10, 5, 4, 8];
@endphp
<table class="head">
    <tr>
        @if ($logo)<td style="width:42px;"><img class="logo" src="{{ $logo }}" alt=""></td>@endif
        <td>
            <h1>{{ $d['title'] }}</h1>
            <div class="sub">{{ $d['subtitle'] }}</div>
        </td>
        <td class="meta">
            {{ count($d['rows']) }} record{{ count($d['rows']) === 1 ? '' : 's' }}<br>
            {{ $d['filters'] }}<br>
            Generated {{ date('M j, Y g:i A') }}
        </td>
    </tr>
</table>
<div class="bar"></div>

<table class="t">
    <colgroup>@foreach ($w as $pct)<col style="width:{{ $pct }}%">@endforeach</colgroup>
    <thead>
        <tr>@foreach ($pick as $i)<th>{{ $d['headers'][$i] }}</th>@endforeach</tr>
    </thead>
    <tbody>
        @forelse ($d['rows'] as $row)
            <tr>
                @foreach ($pick as $n => $i)
                    <td class="{{ $n === 0 ? 'name' : '' }}">{{ $row[$i] !== '' ? $row[$i] : '—' }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($pick) }}" class="muted" style="text-align:center;padding:14px;">No records match these filters.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="foot">e-RESERVE · Supreme Student Council · confidential, for SSC use only</div>
</body>
</html>
