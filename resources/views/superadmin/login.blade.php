@extends('layouts.base')
@section('title', 'Super admin sign in')

@section('body')
<link href="{{ asset_v('assets/css/superadmin.css') }}" rel="stylesheet">
<div class="sa-login">
    <form method="post" action="{{ route('superadmin.login.store') }}" class="box" autocomplete="off">
        @csrf
        <img src="{{ asset(ssc('logo_small')) }}" alt="" width="52" height="52">
        <h1>System console</h1>
        <p>Super admin sign-in. Students and SSC staff use their own pages.</p>

        @if (session('login_error'))<div class="err" role="alert">{{ session('login_error') }}</div>@endif

        <label for="sa-email">Email</label>
        <input type="email" class="form-control" id="sa-email" name="email" value="{{ old('email') }}" required autofocus>
        <label for="sa-pass">Password</label>
        <input type="password" class="form-control" id="sa-pass" name="password" required autocomplete="current-password">

        <button type="submit" class="sa-btn primary w-100 mt-4 py-2"><i class="fa-solid fa-lock"></i> Sign in</button>
        <div class="text-center mt-3"><a href="{{ route('home') }}" style="font-size:.8rem;">Back to the event page</a></div>
    </form>
</div>
@endsection
