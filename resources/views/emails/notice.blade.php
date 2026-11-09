@extends('emails.layout')
@section('title', $subjectLine)
@section('preview', $heading)

@section('body')
  <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:800;color:#173B4D;letter-spacing:-.3px;">{{ $heading }}</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5A6A73;">Hi {{ $studentName }},</p>

  @foreach ($paragraphs as $p)
    <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5A6A73;">{{ $p }}</p>
  @endforeach

  @if ($ticket)
    @php $qrCid = $message->embedData(qr_png(tickets()->qrPayload($ticket)), 'ticket-qr.png', 'image/png'); @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="margin:10px 0 24px;border:1.5px solid #9AD6EE;border-radius:18px;overflow:hidden;background:#ffffff;">
      <tr><td bgcolor="#EEF8FC" style="background:#EEF8FC;padding:12px 20px;border-bottom:1.5px dashed #9AD6EE;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td style="font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#2A7FA6;">Admit one</td>
          <td align="right" style="font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#B87A18;">{{ $ticket['date_text'] }}</td>
        </tr></table>
      </td></tr>
      <tr><td align="center" style="padding:22px 20px 8px;">
        <div style="font-size:17px;font-weight:800;color:#173B4D;line-height:1.3;">{{ $ticket['event'] }}</div>
        <div style="font-size:13px;color:#5A6A73;margin-top:4px;">{{ $ticket['venue'] }} · {{ $ticket['time_text'] }}</div>
      </td></tr>
      <tr><td align="center" style="padding:10px 20px 4px;">
        <img src="{{ $qrCid }}" width="200" height="200" alt="Ticket QR code {{ $ticket['ticket_no'] }}"
             style="display:block;width:200px;height:200px;border:1px solid #E3EBF0;border-radius:12px;">
      </td></tr>
      <tr><td align="center" style="padding:10px 20px 6px;">
        <div style="font-family:Consolas,'SFMono-Regular',Menlo,monospace;font-size:18px;font-weight:700;color:#173B4D;letter-spacing:.5px;">{{ $ticket['ticket_no'] }}</div>
      </td></tr>
      <tr><td style="padding:14px 22px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #E3EBF0;">
          @foreach ([['Name', $ticket['name']], [($ticket['member_type'] ?? 'student') === 'student' ? 'SR-code' : 'Employee ID', $ticket['student_no']], [($ticket['member_type'] ?? 'student') === 'student' ? 'Program' : 'Category', trim(($ticket['program'] ?? '') . ' ' . ($ticket['year'] ?? ''))]] as [$k, $v])
            @if (trim((string) $v) !== '')
              <tr>
                <td style="padding:9px 0 0;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#8697A1;width:84px;vertical-align:top;">{{ $k }}</td>
                <td style="padding:9px 0 0;font-size:14px;font-weight:700;color:#2B373D;">{{ $v }}</td>
              </tr>
            @endif
          @endforeach
        </table>
      </td></tr>
    </table>
  @elseif ($highlight)
    <div style="margin:8px 0 22px;padding:16px 18px;background:#EEF8FC;border:1.5px dashed #9AD6EE;border-radius:14px;text-align:center;">
      <div style="font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#2A7FA6;margin-bottom:6px;">Ticket number</div>
      <div style="font-family:Consolas,'SFMono-Regular',Menlo,monospace;font-size:20px;font-weight:700;color:#173B4D;letter-spacing:.5px;">{{ $highlight }}</div>
    </div>
  @endif

  @if ($buttonUrl)
    <p style="margin:6px 0 4px;">
      <a class="btn" href="{{ $buttonUrl }}"
         style="display:inline-block;background:#E9A93A;background-image:linear-gradient(135deg,#F4BC5B,#E9A93A);color:#3B2405;font-weight:800;font-size:15px;text-decoration:none;padding:14px 30px;border-radius:99px;">{{ $buttonLabel }}</a>
    </p>
  @endif
@endsection
