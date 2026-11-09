@extends('layouts.base')
@section('title', 'Forgot Password')
@section('bodyClass', 'auth-page')

@section('content')
<div class="container" style="max-width:560px;padding:4rem 1rem;">
    <div class="text-center mb-4">
        <a class="brand-lockup d-inline-flex" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset(ssc('logo')) }}" alt="Supreme Student Council seal">
            <span class="brand-text text-start">
                <span class="brand-name d-block">e-<span>RESERVE</span></span>
                <span class="brand-sub">SIGLA 2026</span>
            </span>
        </a>
    </div>

    <div class="card-soft p-4 p-md-5 fade-up">
        <h3 style="font-size:1.4rem;">Forgot your password?</h3>
        <p class="text-soft" style="font-size:.9rem;">
            Students, SSC officers and staff: enter the email of your account and we will send you a link to choose a new password.
        </p>

        @if (session('reset_sent'))
            <div class="callout callout-success"><i class="fa-solid fa-circle-check"></i><div>{{ session('reset_sent') }}</div></div>
        @endif
        @if (session('reset_error'))
            <div class="callout callout-danger"><i class="fa-solid fa-circle-exclamation"></i><div>{{ session('reset_error') }}</div></div>
        @endif

        <form method="post" action="{{ route('password.email') }}" novalidate>
            @csrf
            <label class="form-label-sm d-block" for="email">Account Email Address</label>
            <div class="input-group mb-4">
                <span class="input-group-text"><i class="fa-brands fa-google"></i></span>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}"
                       placeholder="juan.delacruz@g.batstate-u.edu.ph" autocomplete="email" required
                       style="border-left:0;border-radius:0 var(--radius-xs) var(--radius-xs) 0;">
            </div>
            <button type="submit" class="btn btn-green btn-block2 btn-lg2"><i class="fa-solid fa-paper-plane"></i> Send reset link</button>
        </form>

        <p class="text-center mt-4 mb-0" style="font-size:.84rem;">
            <a href="{{ route('login') }}" class="text-gold fw-800"><i class="fa-solid fa-arrow-left me-1"></i> Back to sign in</a>
        </p>
    </div>
</div>
@endsection
