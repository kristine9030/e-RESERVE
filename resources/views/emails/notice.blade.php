@extends('emails.layout')
@section('title', $subjectLine)
@section('preview', $heading)

@php
    $icons   = ['received' => '✓', 'ticket' => '★', 'returned' => '!', 'waitlist' => '…'];
    $labels  = ['received' => 'Documents received', 'ticket' => 'Your ticket', 'returned' => 'Action needed', 'waitlist' => 'You are in line'];
@endphp
@section('icon', $icons[$kind] ?? '@')
@section('eyebrow', $labels[$kind] ?? 'Notice')

@section('body')
  <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:800;color:#173B4D;letter-spacing:-.3px;">{{ $heading }}</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5A6A73;">Hi {{ $studentName }},</p>

  @foreach ($paragraphs as $p)
    @if ($kind === 'returned' && str_starts_with($p, '“'))
      {{-- SSC's note to the student: shown as a quote, so it is not missed --}}
      <div style="margin:0 0 16px;padding:14px 18px;background:#FDF4E3;border-left:4px solid #E9A93A;border-radius:0 12px 12px 0;font-size:15px;line-height:1.7;font-weight:600;color:#6B4710;">{{ $p }}</div>
    @else
      <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#5A6A73;">{{ $p }}</p>
    @endif
  @endforeach

  @if ($ticket)
    {{-- The pass: sky header, gold ticket number, details grid. The QR code is deliberately NOT in the email. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="margin:12px 0 26px;border-radius:20px;overflow:hidden;border:1.5px solid #9AD6EE;background:#ffffff;">
      <tr><td bgcolor="#2A7FA6" style="padding:16px 22px;background-color:#2A7FA6;background-image:radial-gradient(circle at 95% 15%,rgba(255,255,255,.25) 0,rgba(255,255,255,.25) 38px,rgba(255,255,255,0) 39px),linear-gradient(135deg,#2279A3 0%,#3A9ACB 60%,#6CC6EA 100%);">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td style="font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#F4D08A;">Admit one</td>
          <td align="right" style="font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:rgba(255,255,255,.85);">{{ $ticket['date_text'] }}</td>
        </tr></table>
        <div style="font-size:19px;font-weight:800;color:#ffffff;line-height:1.3;margin-top:8px;">{{ $ticket['event'] }}</div>
      </td></tr>

      <tr><td align="center" style="padding:22px 20px 18px;background:#F4FAFD;">
        <div style="font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#7E93A0;margin-bottom:8px;">Ticket number</div>
        <div class="mono" style="display:inline-block;padding:10px 24px;border-radius:99px;background:#173B4D;font-family:Consolas,'SFMono-Regular',Menlo,monospace;font-size:22px;font-weight:700;color:#F4BC5B;letter-spacing:1.5px;">{{ $ticket['ticket_no'] }}</div>
      </td></tr>

      {{-- Tear line --}}
      <tr><td style="padding:0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td width="10" style="width:10px;height:20px;background:#ffffff;border-radius:0 10px 10px 0;border:1.5px solid #9AD6EE;border-left:0;font-size:0;line-height:0;">&nbsp;</td>
          <td style="height:10px;border-bottom:2px dashed #BFE3F3;font-size:0;line-height:0;">&nbsp;</td>
          <td width="10" style="width:10px;height:20px;background:#ffffff;border-radius:10px 0 0 10px;border:1.5px solid #9AD6EE;border-right:0;font-size:0;line-height:0;">&nbsp;</td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:10px 22px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          @php
              $isStudent = ($ticket['member_type'] ?? 'student') === 'student';
              $rows = [
                  ['Name', $ticket['name']],
                  [$isStudent ? 'SR-code' : 'Employee ID', $ticket['student_no']],
                  [$isStudent ? 'Program' : 'Category', trim(($ticket['program'] ?? '') . ' ' . ($ticket['year'] ?? ''))],
                  ['Time', $ticket['time_text']],
                  ['Venue', $ticket['venue']],
              ];
          @endphp
          @foreach ($rows as [$k, $v])
            @if (trim((string) $v) !== '')
              <tr>
                <td style="padding:10px 0 0;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#7E93A0;width:92px;vertical-align:top;">{{ $k }}</td>
                <td style="padding:10px 0 0;font-size:14px;font-weight:700;color:#173B4D;">{{ $v }}</td>
              </tr>
            @endif
          @endforeach
        </table>
      </td></tr>
    </table>

    {{-- How it works, in three short steps --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
      @foreach ([['1', 'Open your ticket', 'Press the button below, or sign in to e-RESERVE and go to My Ticket.'], ['2', 'Show the QR code', 'Hold it up at the gate. It admits one person and is scanned once.'], ['3', 'Enjoy the night', 'Keep your phone charged and your ticket number handy.']] as [$n, $t, $d])
        <tr>
          <td width="34" valign="top" style="padding:0 0 12px;width:34px;">
            <div style="width:26px;height:26px;border-radius:50%;background:#FDF4E3;border:1.5px solid #F4BC5B;text-align:center;font-size:12px;font-weight:800;line-height:23px;color:#B87A18;">{{ $n }}</div>
          </td>
          <td valign="top" style="padding:0 0 12px;">
            <div style="font-size:14px;font-weight:800;color:#173B4D;">{{ $t }}</div>
            <div style="font-size:13px;line-height:1.6;color:#5A6A73;">{{ $d }}</div>
          </td>
        </tr>
      @endforeach
    </table>
  @elseif ($highlight)
    <div style="margin:8px 0 22px;padding:16px 18px;background:#EEF8FC;border:1.5px dashed #9AD6EE;border-radius:14px;text-align:center;">
      <div style="font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#2A7FA6;margin-bottom:6px;">Ticket number</div>
      <div class="mono" style="font-family:Consolas,'SFMono-Regular',Menlo,monospace;font-size:20px;font-weight:700;color:#173B4D;letter-spacing:.5px;">{{ $highlight }}</div>
    </div>
  @endif

  @if ($buttonUrl)
    <p style="margin:6px 0 4px;">
      <a class="btn" href="{{ $buttonUrl }}"
         style="display:inline-block;background:#E9A93A;background-image:linear-gradient(135deg,#F4BC5B,#E9A93A);color:#3B2405;font-weight:800;font-size:15px;text-decoration:none;padding:14px 30px;border-radius:99px;box-shadow:0 6px 16px rgba(233,169,58,.35);">{{ $buttonLabel }} &rarr;</a>
    </p>
  @endif
@endsection
