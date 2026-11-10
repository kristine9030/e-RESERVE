{{-- Shared shell for every e-RESERVE email. Same sky-blue + gold palette and Plus Jakarta Sans as the landing page. --}}
@php
    // Plus Jakarta Sans where the mail app can load it (Apple Mail, iOS, Thunderbird, some Outlook);
    // Gmail and Outlook desktop ignore web fonts, so the fallbacks are geometric faces that look closest to it.
    $font  = "'Plus Jakarta Sans','Avenir Next',Avenir,'Century Gothic','Trebuchet MS',Verdana,Helvetica,Arial,sans-serif";
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
    body, table, td, a, p, h1, div, span, strong { font-family: {!! $font !!} !important; }
    .mono { font-family: Consolas,'SFMono-Regular',Menlo,monospace !important; }
    @media (max-width: 480px) { .px { padding-left: 22px !important; padding-right: 22px !important; } .btn { display:block !important; text-align:center !important; } }
  </style>
</head>
<body style="margin:0;padding:0;background:#EEF8FC;font-family:{!! $font !!};color:#2B373D;-webkit-text-size-adjust:100%;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preview')</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF8FC;padding:28px 12px;">
    <tr><td align="center">

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
             style="max-width:560px;background:#ffffff;border:1px solid #D4E8F1;border-radius:26px;overflow:hidden;box-shadow:0 14px 40px rgba(34,121,163,.16);">

        {{-- Header: the landing page's sky gradient, with the soft light circles of the hero --}}
        <tr><td class="px" bgcolor="#2A7FA6"
                style="background-color:#2A7FA6;background-image:radial-gradient(circle at 94% 12%,rgba(255,255,255,.26) 0,rgba(255,255,255,.26) 46px,rgba(255,255,255,0) 47px),radial-gradient(circle at 80% 105%,rgba(255,255,255,.16) 0,rgba(255,255,255,.16) 70px,rgba(255,255,255,0) 71px),linear-gradient(135deg,#2279A3 0%,#3A9ACB 55%,#6CC6EA 100%);padding:30px 32px 28px;">
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

        <tr><td class="px" style="padding:30px 32px 28px;">
          {{-- Every email opens the same way: round icon badge + small gold label, then its own heading --}}
          <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;"><tr>
            <td width="52" height="52" align="center" valign="middle"
                style="width:52px;height:52px;border-radius:50%;background:#EEF8FC;background-image:linear-gradient(135deg,#EEF8FC,#CDEBF8);border:2px solid #F4BC5B;font-family:Arial,Helvetica,sans-serif !important;font-size:24px;font-weight:800;color:#2A7FA6;line-height:48px;text-align:center;">
              @yield('icon', '@')
            </td>
            <td style="padding-left:14px;vertical-align:middle;">
              <div style="font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#D9932A;">@yield('eyebrow', 'Notice')</div>
              <div style="font-size:12px;font-weight:600;color:#7E93A0;margin-top:2px;">{{ config('ereserve.event.name') }}</div>
            </td>
          </tr></table>

          @yield('body')
        </td></tr>

        {{-- Ticket perforation: dashed tear line with a notch at each edge, like the ticket stub --}}
        <tr><td style="padding:0;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            <td width="12" style="width:12px;height:24px;background:#EEF8FC;border-radius:0 12px 12px 0;border:1px solid #D4E8F1;border-left:0;font-size:0;line-height:0;">&nbsp;</td>
            <td style="height:12px;border-bottom:2px dashed #BFE3F3;font-size:0;line-height:0;">&nbsp;</td>
            <td width="12" style="width:12px;height:24px;background:#EEF8FC;border-radius:12px 0 0 12px;border:1px solid #D4E8F1;border-right:0;font-size:0;line-height:0;">&nbsp;</td>
          </tr></table>
        </td></tr>

        <tr><td class="px" style="background:#F4FAFD;padding:6px 32px 22px;font-size:12px;line-height:1.6;color:#7E93A0;text-align:center;">
          <strong style="color:#2A7FA6;">{{ config('ereserve.event.org') }}</strong><br>
          {{ config('ereserve.event.host') }}<br>
          <span style="font-size:11px;">This is an automated message from e-RESERVE. Please do not reply to it.</span>
        </td></tr>
      </table>

    </td></tr>
  </table>
</body>
</html>
