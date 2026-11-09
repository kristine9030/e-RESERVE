@extends('layouts.app')
@section('title', 'Change Password')
@section('crumb', 'My Account')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card-soft fade-up">
            <div class="card-head"><h5 class="mb-0">{{ $hasPassword ? 'Change password' : 'Set a password' }}</h5></div>
            <div class="card-body-p">
                @unless ($hasPassword)
                    <p class="text-soft" style="font-size:.86rem;">
                        You signed up with Google, so you have no password yet. Set one if you also want to sign in with your email.
                    </p>
                @endunless

                @if (!empty($forced))
                    <div class="callout callout-info"><i class="fa-solid fa-key"></i>
                        <div><strong>First sign-in.</strong> The SSC gave you a temporary password. Type it as the current password,
                        then choose your own. You cannot use the rest of e-RESERVE until you do.</div></div>
                @endif

                @if (session('password_error'))
                    <div class="callout callout-danger"><i class="fa-solid fa-circle-exclamation"></i><div>{{ session('password_error') }}</div></div>
                @endif

                <form method="post" action="{{ route('account.password.update') }}" novalidate>
                    @csrf
                    @if ($hasPassword)
                        <div class="mb-3">
                            <label class="form-label-sm d-block" for="current_password">Current password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label-sm d-block" for="password">New password</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                        <div class="cell-sub mt-1">At least 8 characters, with a letter and a number.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label-sm d-block" for="password_confirm">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save password</button>
                    <a href="{{ is_admin() ? route('admin.dashboard') : route('profile') }}" class="btn btn-ghost ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
