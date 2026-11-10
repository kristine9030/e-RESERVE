@php $nextQ = ($next ?? '') !== '' ? '?next=' . urlencode($next) : ''; @endphp
<nav class="auth-switch" aria-label="Sign in or create an account">
    <a href="{{ route('login') . $nextQ }}" class="{{ $active === 'login' ? 'is-active' : '' }}" @if ($active === 'login') aria-current="page" @endif>
        <i class="fa-solid fa-right-to-bracket"></i> Sign in
    </a>
    <a href="{{ route('register') . $nextQ }}" class="{{ $active === 'register' ? 'is-active' : '' }}" @if ($active === 'register') aria-current="page" @endif>
        <i class="fa-solid fa-user-plus"></i> Create account
    </a>
</nav>
