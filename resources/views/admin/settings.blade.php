@extends('layouts.app')
@section('title', 'Site settings')
@section('crumb', 'Administration')
@section('nav', 'settings')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-sliders"></i> Landing page</span>
            <h2>Site settings</h2>
            <p>Choose what the public landing page shows. Everything stays hidden until you turn it on.</p>
        </div>
    </div>
</div>

<div class="card-soft fade-up">
    <div class="card-head">
        <div><h5 class="mb-0"><i class="fa-solid fa-hourglass-half"></i> Soft launch — student access</h5>
            <span class="cell-sub">The landing page is already public. Student sign-in and account creation stay shut until you open them — your SSC door at <code>/ssc</code> always works, so you can keep testing.</span></div>
        <span class="badge-soft badge-{{ $accessOpen ? 'success' : 'warning' }}">{{ $accessOpen ? 'Open' : 'Closed' }}</span>
    </div>
    <div class="card-body-p">
        <form method="post" action="{{ route('admin.settings.access') }}">@csrf
            <div class="d-flex flex-column gap-2 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="sa-auto" name="mode" value="auto" {{ $accessMode === 'auto' ? 'checked' : '' }}>
                    <label class="form-check-label" for="sa-auto">Follow the schedule — students get in automatically on <strong>{{ $accessOpensAt }}</strong></label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="sa-open" name="mode" value="open" {{ $accessMode === 'open' ? 'checked' : '' }}>
                    <label class="form-check-label" for="sa-open">Open now — anyone may sign in and register right away</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="sa-closed" name="mode" value="closed" {{ $accessMode === 'closed' ? 'checked' : '' }}>
                    <label class="form-check-label" for="sa-closed">Closed until we say so — ignore the schedule</label>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-sm-6">
                    <label class="form-label-sm d-block" for="sa-opens">Scheduled opening (for “follow the schedule”)</label>
                    <input type="datetime-local" class="form-control" id="sa-opens" name="opens_at"
                           value="{{ old('opens_at', $accessOpensAtValue) }}" required>
                </div>
                <div class="col-sm-6">
                    <button type="submit" class="btn btn-gold btn-sm2"><i class="fa-solid fa-floppy-disk"></i> Save student access</button>
                    <span class="cell-sub">No one has to stay up: the doors open on their own once the moment passes.</span>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card-soft fade-up d1 mt-4">
    <div class="card-head"><h5 class="mb-0">What visitors can see</h5></div>
    <div class="card-body-p">
        @foreach ($switches as $key => $sw)
            <div class="info-row">
                <div class="flex-grow-1 min-w-0">
                    <div class="info-value" style="font-size:.95rem;">{{ $sw['label'] }}</div>
                    <div class="cell-sub">{{ $sw['help'] }}</div>
                    @if ($key === 'show_sponsors')
                        <div class="cell-sub mt-1"><i class="fa-solid fa-handshake"></i>
                            {{ $sponsorCount }} active {{ \Illuminate\Support\Str::plural('sponsor', $sponsorCount) }}.
                            <a href="{{ route('admin.sponsors') }}">Manage sponsors</a>
                        </div>
                    @endif
                </div>
                <span class="badge-soft badge-{{ $sw['on'] ? 'success' : 'muted' }}">{{ $sw['on'] ? 'Public' : 'Hidden' }}</span>
                <form method="post" action="{{ route('admin.settings.update') }}">@csrf
                    <input type="hidden" name="key" value="{{ $key }}">
                    <input type="hidden" name="on" value="{{ $sw['on'] ? 0 : 1 }}">
                    <button class="btn {{ $sw['on'] ? 'btn-ghost' : 'btn-gold' }} btn-sm2" type="submit">{{ $sw['on'] ? 'Hide it' : 'Publish it' }}</button>
                </form>
            </div>
        @endforeach
    </div>
</div>

@if ($ex = session('exceed'))
<div class="card-soft fade-up mt-4" style="border:2px solid #d9534f;">
    <div class="card-head"><h5 class="mb-0" style="color:#b02a37;"><i class="fa-solid fa-triangle-exclamation"></i> This is more than the venue holds</h5></div>
    <div class="card-body-p">
        <p style="font-size:.92rem;">
            You are about to offer <strong>{{ number_format($ex['total']) }}</strong> tickets. The venue is set for
            <strong>{{ number_format($ex['base']) }}</strong>, so that is <strong>{{ number_format($ex['total'] - $ex['base']) }} more</strong> than
            planned. Check fire-safety and venue limits first. Nothing has been saved yet.
        </p>
        <form method="post" action="{{ route('admin.settings.quotas') }}" autocomplete="off">@csrf
            @foreach ($ex['limits'] as $g => $n)<input type="hidden" name="quota[{{ $g }}]" value="{{ $n }}">@endforeach
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label-sm d-block" for="cf-total">Type the new total ({{ $ex['total'] }}) to confirm</label>
                    <input type="text" class="form-control" id="cf-total" name="confirm_total" inputmode="numeric" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label-sm d-block" for="cf-pass">Your password</label>
                    <input type="password" class="form-control" id="cf-pass" name="confirm_password" autocomplete="current-password" required>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-gold btn-sm2"><i class="fa-solid fa-lock-open"></i> Yes, offer {{ number_format($ex['total']) }} tickets</button>
                <a href="{{ route('admin.settings') }}" class="btn btn-ghost btn-sm2">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endif

<div class="card-soft fade-up d2 mt-4">
    <div class="card-head">
        <div><h5 class="mb-0">Tickets per group</h5>
            <span class="cell-sub">{{ number_format($capacity) }} seats in all. A group’s unused seats stay on hold for that group until you move them. Going above {{ number_format($base) }} (the venue) asks you to confirm.</span></div>
    </div>
    <div class="card-body-p">
        <form method="post" action="{{ route('admin.settings.quotas') }}">@csrf
            @foreach ($groups as $g => $label)
                @php $used = $taken[$g] ?? 0; $held = max(0, $limits[$g] - $used); @endphp
                <div class="info-row">
                    <div class="flex-grow-1 min-w-0">
                        <label class="info-value" style="font-size:.95rem;" for="q-{{ $g }}">{{ $label }}</label>
                        <div class="cell-sub">{{ number_format($used) }} issued · <strong>{{ number_format($held) }}</strong> on hold</div>
                    </div>
                    <input type="number" class="form-control" style="width:120px;" id="q-{{ $g }}" name="quota[{{ $g }}]"
                           value="{{ old('quota.' . $g, $limits[$g]) }}" min="{{ $used }}" required>
                </div>
            @endforeach
            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                <button type="submit" class="btn btn-gold btn-sm2"><i class="fa-solid fa-floppy-disk"></i> Save tickets</button>
                <span class="cell-sub">Total allotted: {{ number_format(array_sum($limits)) }} · venue {{ number_format($base) }}</span>
            </div>
        </form>
    </div>
</div>

<div class="card-soft fade-up d3 mt-4">
    <div class="card-head">
        <div><h5 class="mb-0">Waiting for the SSC (tickets full)</h5>
            <span class="cell-sub">These people finished uploading after their group's tickets were full. They were told we cannot promise a ticket. Nobody here gets a ticket by itself, not when a seat frees up and not when you raise the allotment. You let them in below, longest-waiting first.</span></div>
    </div>
    <div class="card-body-p">
        @foreach ($groups as $g => $label)
            <form method="post" action="{{ route('admin.settings.release') }}" class="row g-2 align-items-center py-2" style="border-top:1px solid var(--line);">@csrf
                <input type="hidden" name="group" value="{{ $g }}">
                <div class="col-sm-4"><strong>{{ $label }}</strong><div class="cell-sub">{{ $reservedBy[$g] }} of {{ $limits[$g] }} seats spoken for</div></div>
                <div class="col-6 col-sm-2"><b>{{ $waitingBy[$g] }}</b> <span class="cell-sub">waiting</span></div>
                <div class="col-6 col-sm-2"><b>{{ $openBy[$g] }}</b> <span class="cell-sub">open seats</span></div>
                <div class="col-6 col-sm-2"><input type="number" class="form-control" name="count" min="1" max="{{ max(1, min($openBy[$g], $waitingBy[$g])) }}" placeholder="How many" @disabled($openBy[$g] < 1 || $waitingBy[$g] < 1)></div>
                <div class="col-6 col-sm-2"><button type="submit" class="btn btn-gold btn-sm2 w-100" @disabled($openBy[$g] < 1 || $waitingBy[$g] < 1)><i class="fa-solid fa-door-open"></i> Release</button></div>
            </form>
        @endforeach
        <div class="cell-sub mt-2">Open seats appear when someone with a seat is deleted, or when you raise a group's allotment above.</div>
    </div>
</div>

<div class="card-soft fade-up d3 mt-4">
    <div class="card-head">
        <div><h5 class="mb-0">Give unused seats to another group</h5>
            <span class="cell-sub">For example, sponsors did not use all 300: move the rest to students. Then release them for the waiting list above, if you choose to.</span></div>
    </div>
    <div class="card-body-p">
        <form method="post" action="{{ route('admin.settings.transfer') }}" class="row g-3 align-items-end">@csrf
            <div class="col-sm-4">
                <label class="form-label-sm d-block" for="mv-from">From</label>
                <select class="form-select" id="mv-from" name="from">
                    @foreach ($groups as $g => $label)<option value="{{ $g }}">{{ $label }} ({{ max(0, $limits[$g] - max($taken[$g] ?? 0, $reservedBy[$g] ?? 0)) }} unused)</option>@endforeach
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label-sm d-block" for="mv-to">To</label>
                <select class="form-select" id="mv-to" name="to">
                    @foreach ($groups as $g => $label)<option value="{{ $g }}" {{ $g === 'student' ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-sm-2">
                <label class="form-label-sm d-block" for="mv-n">Seats</label>
                <input type="number" class="form-control" id="mv-n" name="count" min="1" required>
            </div>
            <div class="col-6 col-sm-2">
                <button type="submit" class="btn btn-ghost btn-block2"><i class="fa-solid fa-right-left"></i> Move</button>
            </div>
        </form>
    </div>
</div>
@endsection
