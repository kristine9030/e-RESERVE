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

        <div class="auth-card fade-up" style="max-width:600px;">
            <a href="{{ route('home') }}" class="auth-back">
                <i class="fa-solid fa-arrow-left"></i> Back to event page
            </a>

            @include('partials.auth_switch', ['active' => 'register'])

            <h3 style="font-size:1.55rem;">Create your account</h3>
            <p class="text-soft mb-4" style="font-size:.87rem;">
                First, tell us who you are. It takes about a minute.
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
                    <strong>I’m faculty, an instructor or staff</strong>
                    <span>Your name and employee ID, plus one PDF of your employee ID. Your ticket is issued once SSC approves it.</span>
                </span>
                <i class="fa-solid fa-chevron-right who-go"></i>
            </a>

            <a href="{{ route('register', ['as' => 'sponsor']) . $q }}" class="who-card">
                <span class="who-ico who-ico-orange"><i class="fa-solid fa-handshake"></i></span>
                <span class="who-txt">
                    <strong>I’m a sponsor</strong>
                    <span>Representing a company or partner of SIGLA 2026. Use any email, confirm it, and upload your proof of sponsorship.</span>
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
