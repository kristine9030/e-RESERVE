@extends('emails.layout')
@section('title', 'New sponsor inquiry')
@section('preview', $inquiry->company . ' wants to sponsor ' . config('ereserve.event.name'))
@section('icon', '♥')
@section('eyebrow', 'Sponsor inquiry')

@section('body')
  <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:800;color:#173B4D;letter-spacing:-.3px;">{{ $inquiry->company }} wants to sponsor</h1>
  <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#5A6A73;">Someone sent the sponsor form on the e-RESERVE landing page. <strong>Press Reply</strong> to answer them directly.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#F4FAFD;border:1.5px solid #BFE3F3;border-radius:14px;">
    @foreach ([['Name', $inquiry->contact_name], ['Company', $inquiry->company], ['Email', $inquiry->email], ['Phone', $inquiry->phone]] as [$k, $v])
      @if (filled($v))
        <tr>
          <td style="padding:10px 16px 0;width:90px;font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#7E93A0;vertical-align:top;">{{ $k }}</td>
          <td style="padding:10px 16px 0;font-size:14px;font-weight:700;color:#173B4D;">{{ $v }}</td>
        </tr>
      @endif
    @endforeach
    <tr><td colspan="2" style="height:12px;font-size:0;line-height:0;">&nbsp;</td></tr>
  </table>

  @if (filled($inquiry->message))
    <div style="margin:0 0 16px;padding:14px 18px;background:#FDF4E3;border-left:4px solid #E9A93A;border-radius:0 12px 12px 0;font-size:15px;line-height:1.7;color:#6B4710;white-space:pre-line;">{{ $inquiry->message }}</div>
  @endif

  <p style="margin:6px 0 4px;">
    <a href="{{ route('admin.sponsors') }}"
       style="display:inline-block;background:#E9A93A;background-image:linear-gradient(135deg,#F4BC5B,#E9A93A);color:#3B2405;font-weight:800;font-size:15px;text-decoration:none;padding:14px 30px;border-radius:99px;">Open sponsor inquiries &rarr;</a>
  </p>
@endsection
