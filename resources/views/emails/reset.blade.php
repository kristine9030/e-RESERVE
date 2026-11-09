@extends('emails.layout')
@section('title', 'Reset your e-RESERVE password')
@section('preview', 'Choose a new password. The link works for ' . $minutes . ' minutes.')

@section('body')
  <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:800;color:#173B4D;letter-spacing:-.3px;">Reset your password</h1>
  <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#5A6A73;">
    Hi {{ $studentName }}, we got a request to reset the password of your e-RESERVE account.
    Press the button to choose a new one.
  </p>

  <p style="margin:0 0 26px;">
    <a class="btn" href="{{ $link }}"
       style="display:inline-block;background:#E9A93A;background-image:linear-gradient(135deg,#F4BC5B,#E9A93A);color:#3B2405;font-weight:800;font-size:15px;text-decoration:none;padding:14px 30px;border-radius:99px;">
      Choose a new password
    </a>
  </p>

  <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#8697A1;">
    The link works for {{ $minutes }} minutes and only once. If the button does not work, paste this into your browser:
  </p>
  <p style="margin:0 0 22px;font-size:12px;line-height:1.6;word-break:break-all;">
    <a href="{{ $link }}" style="color:#2A7FA6;">{{ $link }}</a>
  </p>

  <p style="margin:0;padding-top:18px;border-top:1px solid #E3EBF0;font-size:12px;line-height:1.7;color:#8697A1;">
    Did not ask for this? Ignore this email. Your password stays the same.
  </p>
@endsection
