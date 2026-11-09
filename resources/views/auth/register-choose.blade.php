@extends('layouts.base')
@section('title', 'Create Account')
@section('bodyClass', 'auth-page')

@section('content')
<?php $q = $next !== '' ? '&next=' . urlencode($next) : ''; ?>
<div class="auth-shell">

    @include('partials.auth_aside', ['asideMode' => 'register'])

    <main class="auth-main">
        <span class="orb orb-gold orb-soft" style="width:340px;height:340px;top:-100px;right:-120px;"></span>
        <span class="orb orb-teal orb-soft" style="width:300px;height:300px;bottom:-120px;left:-100px;"></span>

        <div class="auth-card fade-up" style="max-width:520px;">
            <a href="{{ route('home') }}" class="text-muted2 fw-800 d-inline-flex align-items-center gap-2 mb-4"
               style="font-size:.76rem;letter-spacing:1.2px;text-transform:uppercase;">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            <h3 style="font-size:1.55rem;">Create your account</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                First, tell us who you are. Already registered?
                <a href="{{ route('login') . ($next !== '' ? '?next=' . urlencode($next) : '') }}" class="text-gold fw-800">Sign in</a>.
            </p>

            <a href="{{ route('register', ['as' => 'student']) . $q }}" class="who-card">
                <span class="who-ico"><i class="fa-solid fa-user-graduate"></i></span>
                <span class="who-txt">
                    <strong>I’m a student</strong>
                    <span>Enrolled at BatStateU ARASOF-Nasugbu. Upload your waiver, COR and school ID; your ticket comes once SSC approves them.</span>
                </span>
                <i class="fa-solid fa-chevron-right who-go"></i>
            </a>

            <a href="{{ route('register', ['as' => 'employee']) . $q }}" class="who-card">
                <span class="who-ico who-ico-gold"><i class="fa-solid fa-briefcase"></i></span>
                <span class="who-txt">
                    <strong>I’m faculty, an instructor, staff or administration</strong>
                    <span>Just your name and employee ID. Confirm your email and your ticket is ready. No documents.</span>
                </span>
                <i class="fa-solid fa-chevron-right who-go"></i>
            </a>

            <p class="text-center text-muted2 mt-4 mb-0" style="font-size:.74rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>
                Your details are used only for SIGLA 2026 ticketing, under the BatStateU Data Privacy Policy.
            </p>
        </div>
    </main>
</div>
@endsection
