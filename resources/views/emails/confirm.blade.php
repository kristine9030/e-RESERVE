@extends('emails.layout')
@section('title', 'Confirm your e-RESERVE account')
@section('preview', 'Confirm this address to start uploading your documents.')

@section('icon', '@')
@section('eyebrow', 'Confirm your account')

@section('body')
  <h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;font-weight:800;color:#173B4D;letter-spacing:-.3px;">Confirm your email address</h1>
  <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#5A6A73;">
    Hi {{ $studentName }}, thanks for registering for {{ config('ereserve.event.name') }}.
    Confirm this address so we know the account is really yours. You need it before you can upload your documents.
  </p>

  <p style="margin:0 0 26px;">
    <a class="btn" href="{{ $link }}"
       style="display:inline-block;background:#E9A93A;background-image:linear-gradient(135deg,#F4BC5B,#E9A93A);color:#3B2405;font-weight:800;font-size:15px;text-decoration:none;padding:14px 30px;border-radius:99px;box-shadow:0 6px 16px rgba(233,169,58,.35);">
      Confirm my email &rarr;
    </a>
  </p>

  <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#8697A1;">
    The link expires in {{ $hours }} hours. If the button does not work, paste this into your browser:
  </p>
  <p style="margin:0 0 22px;font-size:12px;line-height:1.6;word-break:break-all;">
    <a href="{{ $link }}" style="color:#2A7FA6;">{{ $link }}</a>
  </p>

  <p style="margin:0;padding-top:18px;border-top:1px solid #E3EBF0;font-size:12px;line-height:1.7;color:#8697A1;">
    If you did not create this account, ignore this message. No ticket was issued and nothing was submitted in your name.
  </p>
@endsection
