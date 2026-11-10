@extends('layouts.base')
@section('title', 'Choose a New Password')
@section('bodyClass', 'auth-page')

@section('content')
<div class="container" style="max-width:560px;padding:4rem 1rem;">
    <div class="text-center mb-4">
        <a class="brand-lockup d-inline-flex" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
            <span class="brand-text text-start">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="card-soft p-4 p-md-5 fade-up">
        <h3 style="font-size:1.4rem;">Choose a new password</h3>
        <p class="text-soft" style="font-size:.9rem;">At least 8 characters, with a letter and a number.</p>

        @if (session('reset_error'))
            <div class="callout callout-danger"><i class="fa-solid fa-circle-exclamation"></i>
                <div>{{ session('reset_error') }} <a href="{{ route('password.request') }}" class="text-gold fw-800">Request a new link</a></div></div>
        @endif

        <form method="post" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label-sm d-block" for="email">GSuite Email Address</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label-sm d-block" for="password">New password</label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="mb-4">
                <label class="form-label-sm d-block" for="password_confirm">Confirm new password</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-green btn-block2 btn-lg2"><i class="fa-solid fa-key"></i> Save new password</button>
        </form>
    </div>
</div>
@endsection
