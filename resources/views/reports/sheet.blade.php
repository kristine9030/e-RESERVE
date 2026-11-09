@php
    $cols = $ds['columns'];
    $span = count($cols);
    $ink = '#10241D'; $green = '#0B3B2C'; $gold = '#B87A18'; $zebra = '#F4F9F6';
    $border = 'border:.5pt solid #C9DBD3;';
    $pad = 'padding:5px 8px;';
    $head = fn(string $text, string $style) => '<tr><td colspan="' . $span . '" style="' . $style . '">' . e($text) . '</td></tr>';
@endphp
<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">
<style>td,th{font-family:Calibri,Arial,sans-serif;font-size:11pt;vertical-align:middle;}.txt{mso-number-format:"\@";}</style></head><body>
<table cellspacing="0" cellpadding="0">
<colgroup>@foreach ($cols as $c)<col width="{{ (int) $c['width'] }}">@endforeach</colgroup>
{!! $head(ev('host'), $pad . 'font-size:10pt;color:#4A5F58;text-align:center;') !!}
{!! $head(ev('org'), $pad . 'font-size:10pt;color:#4A5F58;text-align:center;') !!}
{!! $head($ds['title'], $pad . 'font-size:18pt;font-weight:bold;color:' . $green . ';text-align:center;') !!}
{!! $head($ds['subtitle'], $pad . 'font-size:10pt;color:#4A5F58;text-align:center;') !!}
{!! $head(ev('name') . ' · ' . ev('date_text') . ' · ' . ev('venue'), $pad . 'font-size:10pt;color:' . $gold . ';font-weight:bold;text-align:center;') !!}
{!! $head('Report Code ' . $ds['code'] . '   |   Generated ' . date('F d, Y · g:i A') . '   |   ' . app_name() . ' ' . config('ereserve.version'), $pad . 'font-size:9pt;color:#7C8F88;text-align:center;') !!}
<tr><td colspan="{{ $span }}" style="height:8px;"></td></tr>
<tr>
@foreach ($cols as $c)
    <th style="{{ $pad . $border }}background:{{ $green }};color:#FFFFFF;font-size:10pt;font-weight:bold;text-align:{{ $rpt->align($c['type']) }};">{{ $c['label'] }}</th>
@endforeach
</tr>
@foreach ($ds['rows'] as $i => $row)
    <tr>
    @foreach ($row as $c => $value)
        <td class="txt" style="{{ $pad . $border . ($i % 2 ? 'background:' . $zebra . ';' : '') }}color:{{ $ink }};text-align:{{ $rpt->align($cols[$c]['type']) }};">{{ $rpt->raw($cols[$c]['type'], $value) }}</td>
    @endforeach
    </tr>
@endforeach
@if (!$ds['rows'])
    <tr><td colspan="{{ $span }}" style="{{ $pad . $border }}text-align:center;color:#7C8F88;">No records for this period.</td></tr>
@endif
<tr><td colspan="{{ $span }}" style="height:14px;"></td></tr>
<tr><td colspan="{{ $span }}" style="{{ $pad }}font-size:11pt;font-weight:bold;color:{{ $green }};">SUMMARY</td></tr>
@foreach ($ds['summary'] as [$label, $value, $type])
    <tr>
        <td colspan="2" style="{{ $pad . $border }}background:#EAF4EF;font-weight:bold;color:{{ $green }};">{{ $label }}</td>
        <td colspan="2" class="txt" style="{{ $pad . $border }}font-weight:bold;text-align:right;color:{{ $ink }};">{{ $rpt->raw($type, $value) }}</td>
        <td colspan="{{ max(1, $span - 4) }}" style="border:0;"></td>
    </tr>
@endforeach
<tr><td colspan="{{ $span }}" style="height:24px;"></td></tr>
<tr>@foreach ($ds['signatories'] as [$role, $who])<td colspan="3" style="{{ $pad }}font-size:10pt;color:#4A5F58;">{{ $role }}:</td>@endforeach</tr>
<tr><td colspan="{{ $span }}" style="height:26px;"></td></tr>
<tr>@foreach ($ds['signatories'] as [$role, $who])<td colspan="3" style="{{ $pad }}border-top:1pt solid {{ $ink }};font-size:10pt;font-weight:bold;color:{{ $ink }};">{{ $who }}</td>@endforeach</tr>
</table></body></html>
