@extends('layouts.app')
@section('title', 'SSC Team & Accounts')
@section('crumb', 'Administration')
@section('nav', 'team')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-people-group"></i> A.Y. 2026-2027</span>
            <h2>SSC Team &amp; Accounts</h2>
            <p>Everyone in the council, by position. Type their GSuite email and create their login. <strong class="text-white">Every SSC account has full access.</strong></p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="tg-label mb-1">With a login</div>
            <div class="fw-900" style="font-size:2.6rem;line-height:1;color:var(--gold-400);letter-spacing:-2px;">
                {{ $active }}<span style="font-size:1.4rem;color:rgba(255,255,255,.55);"> / {{ $total }}</span>
            </div>
        </div>
    </div>
</div>

<div class="callout callout-info fade-up">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        Use each person’s own <strong>{{ $domains }}</strong> address. A password is made for them and shown to you <strong>once</strong>;
        give it to them privately. They sign in at <span class="mono">/ssc</span>. Use <em>Reset password</em> if they forget it.
    </div>
</div>

@foreach (\App\Models\SscMember::GROUPS as $key => $label)
    @php $rows = $groups[$key] ?? collect(); @endphp
    @continue($rows->isEmpty())

    <div class="card-soft mb-4 fade-up">
        <div class="card-head">
            <div>
                <h5 class="mb-0">{{ $label }}</h5>
                <span class="cell-sub">{{ $rows->count() }} {{ $rows->count() === 1 ? 'person' : 'people' }}
                    · {{ $rows->filter(fn ($m) => $m->user && $m->user->status === 'active')->count() }} with a login</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-stack">
                <thead>
                    <tr><th>Position</th><th>Name</th><th style="min-width:270px">GSuite email</th><th>Login</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody>
                @foreach ($rows as $m)
                    @php
                        $has = (bool) $m->user;
                        $on  = $has && $m->user->status === 'active';
                        $me  = $has && $m->user->id === auth()->id();
                    @endphp
                    <tr>
                        <td data-label="Position">
                            <div class="cell-strong">{{ $m->position }}</div>
                        </td>
                        <td data-label="Name"><div class="cell-strong" style="font-weight:700">{{ $m->display_name }}</div></td>
                        <td data-label="Email">
                            @if ($has)
                                <span class="mono" style="font-size:.84rem">{{ $m->email }}</span>
                            @else
                                <input type="email" name="email" form="acc{{ $m->id }}" class="form-control form-control-sm"
                                       placeholder="name@g.batstate-u.edu.ph" value="{{ $m->email }}" autocomplete="off">
                            @endif
                        </td>
                        <td data-label="Login">
                            @if (!$has)
                                <span class="badge-soft badge-muted">No login yet</span>
                            @elseif ($on)
                                <span class="badge-soft badge-success">Active</span>
                                <div class="cell-sub">{{ $m->user->last_login_at ? 'Last in ' . $m->user->last_login_at->diffForHumans() : 'Never signed in' }}</div>
                            @else
                                <span class="badge-soft badge-danger">Turned off</span>
                            @endif
                        </td>
                        <td data-label="Action" class="text-end">
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                <form id="acc{{ $m->id }}" method="post" action="{{ route('admin.team.account', $m->id) }}">
                                    @csrf
                                    @if ($has)
                                        <button class="btn btn-ghost btn-sm2"
                                                data-confirm-title="Reset password for {{ $m->display_name }}?"
                                                data-confirm-text="A new password is made and shown once. The old one stops working."
                                                data-confirm-icon="warning" data-confirm-yes="Reset password">
                                            <i class="fa-solid fa-key"></i> Reset password
                                        </button>
                                    @else
                                        <button class="btn btn-gold btn-sm2"><i class="fa-solid fa-user-plus"></i> Create account</button>
                                    @endif
                                </form>
                                @if ($has && !$me)
                                    <form method="post" action="{{ route('admin.team.access', $m->id) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $on ? 'disable' : 'enable' }}">
                                        <button class="btn btn-sm2 {{ $on ? 'btn-outline-danger' : 'btn-green' }}"
                                                @if ($on) data-confirm-title="Turn off access for {{ $m->display_name }}?"
                                                          data-confirm-text="They will not be able to sign in until you turn it back on."
                                                          data-confirm-icon="warning" data-confirm-yes="Turn off" @endif>
                                            <i class="fa-solid {{ $on ? 'fa-ban' : 'fa-rotate-left' }}"></i> {{ $on ? 'Turn off' : 'Turn on' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

<p class="cell-sub text-center mb-4">Gate-only scanner accounts are separate. Make them with <span class="mono">php artisan ereserve:make-admin gate@g.batstate-u.edu.ph --role=scanner</span>.</p>
@endsection
