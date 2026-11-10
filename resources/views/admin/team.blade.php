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
            <p>Create a login for each council member. Every SSC account has full access.</p>
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
        Use each person’s own <strong>{{ $domains }}</strong> address. If they already registered (student, faculty or sponsor), <strong>Give access</strong> adds SSC access to
        that same account: <strong>same email and password</strong>, no new login. They open the SSC panel at <span class="mono">/ssc</span> and can switch back to the student side anytime.
        Only people who never registered get a separate login, with a password shown to you <strong>once</strong>.
    </div>
</div>

@php
    $all  = $groups->flatten(1);
    $stat = fn ($m) => !$m->user ? 'none' : ($m->hasAccess() ? 'active' : 'off');
    $cnt  = [
        'all'    => $all->count(),
        'none'   => $all->filter(fn ($m) => $stat($m) === 'none')->count(),
        'active' => $all->filter(fn ($m) => $stat($m) === 'active')->count(),
        'off'    => $all->filter(fn ($m) => $stat($m) === 'off')->count(),
    ];
@endphp

<div class="card-soft fade-up mb-4 tm-card">
    <div class="tm-toolbar">
        <div class="stu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="tmSearch" class="form-control" placeholder="Search name, position or email" autocomplete="off">
        </div>
        <nav class="stu-tabs tm-tabs" aria-label="Filter by login status">
            @foreach (['all' => ['Everyone', 'fa-users'], 'none' => ['No login yet', 'fa-user-clock'], 'active' => ['Active', 'fa-circle-check'], 'off' => ['Turned off', 'fa-ban']] as $k => [$lbl, $ic])
                <button type="button" class="stu-tab {{ $k === 'all' ? 'is-active' : '' }}" data-tm-filter="{{ $k }}"><i class="fa-solid {{ $ic }}"></i>{{ $lbl }} <b>{{ $cnt[$k] }}</b></button>
            @endforeach
        </nav>
    </div>

    <div class="tm-head" aria-hidden="true"><span>Member</span><span>GSuite email</span><span>Login</span><span class="text-end">Action</span></div>

    @foreach (\App\Models\SscMember::GROUPS as $key => $label)
        @php $rows = $groups[$key] ?? collect(); @endphp
        @continue($rows->isEmpty())

        <section class="tm-group" data-tm-group>
            <div class="tm-grp-title">
                <span>{{ $label }}</span>
                <small>{{ $rows->count() }} {{ $rows->count() === 1 ? 'person' : 'people' }} · {{ $rows->filter(fn ($m) => $stat($m) === 'active')->count() }} with a login</small>
            </div>

            @foreach ($rows as $m)
                @php
                    $has = (bool) $m->user;
                    $on  = $m->hasAccess();
                    $stu = $m->isStudentLink();
                    $me  = $has && $m->user->id === auth()->id();
                    $ini = strtoupper(mb_substr($m->display_name, 0, 1));
                @endphp
                <div class="tm-row" data-tm-row data-state="{{ $stat($m) }}" data-text="{{ strtolower($m->display_name . ' ' . $m->position . ' ' . $m->email) }}">
                    <div class="tm-who">
                        <span class="tm-av tm-av-{{ $stat($m) }}">{{ $ini }}</span>
                        <div class="min-w-0">
                            <div class="cell-strong">{{ $m->display_name }}@if ($me) <span class="badge-soft badge-info ms-1">You</span>@endif</div>
                            <div class="cell-sub">{{ $m->position }}</div>
                        </div>
                    </div>

                    <div class="tm-mail">
                        @if ($has)
                            <span class="mono tm-mail-txt" title="{{ $m->email }}">{{ $m->email }}</span>
                        @else
                            <input type="email" name="email" form="acc{{ $m->id }}" class="form-control form-control-sm"
                                   placeholder="name@g.batstate-u.edu.ph" value="{{ $m->email }}" autocomplete="off" aria-label="GSuite email for {{ $m->display_name }}">
                        @endif
                    </div>

                    <div class="tm-state">
                        @if (!$has)
                            <span class="badge-soft badge-muted">No login yet</span>
                        @elseif ($on)
                            <span class="badge-soft badge-success">Active</span>
                            <div class="cell-sub">{{ $stu ? 'Student password' : ($m->user->last_login_at ? 'Last in ' . $m->user->last_login_at->diffForHumans() : 'Never signed in') }}</div>
                        @else
                            <span class="badge-soft badge-danger">Turned off</span>
                        @endif
                    </div>

                    <div class="tm-act">
                        <form id="acc{{ $m->id }}" method="post" action="{{ route('admin.team.account', $m->id) }}">
                            @csrf
                            @if ($has && $stu)
                                {{-- Uses their own student password: nothing to reset here. --}}
                            @elseif ($has)
                                <button class="btn btn-ghost btn-sm2" title="Reset password"
                                        data-confirm-title="Reset password for {{ $m->display_name }}?"
                                        data-confirm-text="A new password is made and shown once. The old one stops working."
                                        data-confirm-icon="warning" data-confirm-yes="Reset password">
                                    <i class="fa-solid fa-key"></i> Reset
                                </button>
                            @else
                                <button class="btn btn-green btn-sm2" title="Gives SSC access to their account if they registered, otherwise makes a new login"><i class="fa-solid fa-user-plus"></i> Give access</button>
                            @endif
                        </form>
                        @if ($has && !$me)
                            <form method="post" action="{{ route('admin.team.access', $m->id) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $on ? 'disable' : 'enable' }}">
                                <button class="btn btn-sm2 {{ $on ? 'btn-outline-danger' : 'btn-green' }}" title="{{ $on ? 'Turn off access' : 'Turn access back on' }}"
                                        @if ($on) data-confirm-title="Turn off access for {{ $m->display_name }}?"
                                                  data-confirm-text="They will not be able to sign in until you turn it back on."
                                                  data-confirm-icon="warning" data-confirm-yes="Turn off" @endif>
                                    <i class="fa-solid {{ $on ? 'fa-ban' : 'fa-rotate-left' }}"></i> {{ $on ? 'Turn off' : 'Turn on' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach

    <div class="text-center py-5" id="tmEmpty" hidden>
        <h5 class="mb-1">No one matches</h5>
        <p class="text-soft mb-0" style="font-size:.88rem;">Try a different search or status.</p>
    </div>
</div>

<style>
    .tm-toolbar { display: flex; gap: .75rem 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 1.25rem 1.5rem 1rem; }
    .tm-toolbar .stu-search { flex: 1 1 280px; max-width: 420px; }
    .tm-tabs { padding: 0; }
    .tm-tabs .stu-tab { cursor: pointer; font-family: inherit; }
    .tm-head, .tm-row { display: grid; grid-template-columns: minmax(220px, 1.3fr) minmax(240px, 1.2fr) 150px 190px; gap: 1rem; align-items: center; padding: .7rem 1.5rem; }
    .tm-head { background: #F1F7FB; border-top: 1px solid #E3EEF4; border-bottom: 1px solid #E3EEF4; font-size: .72rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #6B8596; padding-top: .55rem; padding-bottom: .55rem; }
    .tm-head span:last-child { text-align: right; }
    .tm-grp-title { display: flex; align-items: baseline; gap: .75rem; flex-wrap: wrap; padding: .9rem 1.5rem .5rem; font-weight: 800; color: #173B4D; background: #FBFDFE; border-bottom: 1px solid #EDF3F7; }
    .tm-grp-title small { font-weight: 600; color: #6B8596; font-size: .78rem; }
    .tm-row { border-bottom: 1px solid #EDF3F7; }
    .tm-row:hover { background: #F7FBFD; }
    .tm-group[hidden], .tm-row[hidden] { display: none; }
    .tm-who { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .tm-av { flex: 0 0 38px; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; background: #E6F2F9; color: #1F6E94; }
    .tm-av-none { background: #F1F4F6; color: #8499A6; }
    .tm-av-off { background: #FDECEC; color: #C0392B; }
    .tm-mail { min-width: 0; }
    .tm-mail-txt { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .84rem; }
    .tm-act { display: flex; gap: .4rem; justify-content: flex-end; flex-wrap: wrap; }
    .tm-act form { margin: 0; }
    .min-w-0 { min-width: 0; }
    @media (max-width: 991.98px) {
        .tm-head { display: none; }
        .tm-row { grid-template-columns: 1fr auto; gap: .6rem 1rem; padding: .9rem 1rem; }
        .tm-who, .tm-mail { grid-column: 1 / -1; }
        .tm-act { justify-content: flex-start; }
        .tm-toolbar, .tm-grp-title { padding-left: 1rem; padding-right: 1rem; }
    }
</style>
<script>
(function () {
    var search = document.getElementById('tmSearch'),
        rows = [].slice.call(document.querySelectorAll('[data-tm-row]')),
        groups = [].slice.call(document.querySelectorAll('[data-tm-group]')),
        tabs = [].slice.call(document.querySelectorAll('[data-tm-filter]')),
        empty = document.getElementById('tmEmpty'), state = 'all';
    function apply() {
        var q = search.value.trim().toLowerCase(), shown = 0;
        rows.forEach(function (r) {
            var ok = (state === 'all' || r.dataset.state === state) && (!q || r.dataset.text.indexOf(q) !== -1);
            r.hidden = !ok; if (ok) shown++;
        });
        groups.forEach(function (g) { g.hidden = !g.querySelector('[data-tm-row]:not([hidden])'); });
        empty.hidden = shown > 0;
    }
    search.addEventListener('input', apply);
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            state = t.dataset.tmFilter;
            tabs.forEach(function (x) { x.classList.toggle('is-active', x === t); });
            apply();
        });
    });
})();
</script>

<p class="cell-sub text-center mb-4">Gate-only scanner accounts are separate. Make them with <span class="mono">php artisan ereserve:make-admin gate@g.batstate-u.edu.ph --role=scanner</span>.</p>
@endsection
