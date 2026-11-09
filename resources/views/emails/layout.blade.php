{{-- Shared shell for every e-RESERVE email. Same sky-blue + gold palette and Plus Jakarta Sans as the landing page. --}}
@php
    $font  = "'Plus Jakarta Sans','Segoe UI',Helvetica,Arial,sans-serif";
    $url   = (string) config('app.url');
    // The seal can only load for the recipient if the site is publicly reachable.
    $logo  = (str_starts_with($url, 'https://') && !str_contains($url, 'localhost')) ? asset(ssc('logo')) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>@yield('title', 'e-RESERVE')</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap');
    body, table, td, a, p, h1 { font-family: {!! $font !!}; }
    @media (max-width: 480px) { .px { padding-left: 22px !important; padding-right: 22px !important; } .btn { display:block !important; text-align:center !important; } }
  </style>
</head>
<body style="margin:0;padding:0;background:#EEF8FC;font-family:{!! $font !!};color:#2B373D;-webkit-text-size-adjust:100%;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preview')</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF8FC;padding:28px 12px;">
    <tr><td align="center">

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
             style="max-width:560px;background:#ffffff;border:1px solid #E3EBF0;border-radius:20px;overflow:hidden;box-shadow:0 8px 28px rgba(23,59,77,.08);">

        {{-- Header: the landing page's sky gradient --}}
        <tr><td class="px" bgcolor="#2A7FA6"
                style="background-color:#2A7FA6;background-image:linear-gradient(135deg,#2279A3 0%,#3A9ACB 55%,#6CC6EA 100%);padding:26px 32px;">
          <table role="presentation" cellpadding="0" cellspacing="0"><tr>
            @if ($logo)
              <td style="padding-right:14px;vertical-align:middle;">
                <img src="{{ $logo }}" width="44" height="44" alt="" style="display:block;border-radius:50%;background:#fff;">
              </td>
            @endif
            <td style="vertical-align:middle;">
              <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-.5px;line-height:1.1;">
                e-<span style="color:#F4BC5B;">RESERVE</span>
              </div>
              <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.8);margin-top:4px;">
                {{ config('ereserve.event.name') }}
              </div>
            </td>
          </tr></table>
        </td></tr>

        {{-- Gold accent line, like the ticket edge --}}
        <tr><td style="height:4px;line-height:4px;font-size:0;background:#E9A93A;background-image:linear-gradient(90deg,#F4BC5B,#E9A93A,#D9932A);">&nbsp;</td></tr>

        <tr><td class="px" style="padding:32px 32px 28px;">
          @yield('body')
        </td></tr>

        <tr><td class="px" style="background:#F8FAFB;border-top:1px solid #E3EBF0;padding:18px 32px;font-size:12px;line-height:1.6;color:#8697A1;text-align:center;">
          <strong style="color:#5A6A73;">{{ config('ereserve.event.org') }}</strong><br>
          {{ config('ereserve.event.host') }}<br>
          <span style="font-size:11px;">This is an automated message from e-RESERVE. Please do not reply to it.</span>
        </td></tr>
      </table>

    </td></tr>
  </table>
</body>
</html>
