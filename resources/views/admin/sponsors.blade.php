@extends('layouts.app')
@section('title', 'Sponsors')
@section('crumb', 'Administration')
@section('nav', 'sponsors')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-handshake"></i> Partners</span>
            <h2>Sponsors</h2>
            <p>Add the sponsors shown on the event page and read their messages.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;"><i class="fa-solid fa-envelope"></i> {{ $inquiries->where('status', 'new')->count() }} new</span>
        </div>
    </div>
</div>

@unless ($published)
    <div class="callout callout-warning mb-3" style="font-size:.86rem;">
        <i class="fa-solid fa-eye-slash me-1"></i>
        The sponsors section is <strong>hidden</strong> on the landing page. Add sponsors below, then publish it in
        <a href="{{ route('admin.settings') }}">Site Settings</a> when you are ready.
    </div>
@endunless

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-soft fade-up d1">
            <div class="card-head"><h5 class="mb-0">Add a sponsor</h5></div>
            <div class="card-body-p">
                <form method="post" action="{{ route('admin.sponsors.store') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label-sm d-block" for="sp-name">Name</label>
                    <input type="text" class="form-control" id="sp-name" name="name" value="{{ old('name') }}" maxlength="120" required>

                    <label class="form-label-sm d-block mt-3" for="sp-logo">Logo <span class="text-muted2">(PNG, JPG or WebP, up to 2 MB)</span></label>
                    <input type="file" class="form-control" id="sp-logo" name="logo" accept="image/png,image/jpeg,image/webp">
                    <div class="cell-sub mt-1">A transparent PNG looks best. Without a logo, the name is shown as text.</div>

                    <label class="form-label-sm d-block mt-3" for="sp-web">Website <span class="text-muted2">(optional)</span></label>
                    <input type="url" class="form-control" id="sp-web" name="website" value="{{ old('website') }}" placeholder="https://" maxlength="190">

                    <button type="submit" class="btn btn-gold btn-block2 mt-4"><i class="fa-solid fa-plus"></i> Add sponsor</button>
                    <div class="cell-sub mt-2"><i class="fa-solid fa-circle-info"></i> Only add sponsors who agreed to be shown.</div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-soft fade-up d2">
            <div class="card-head"><h5 class="mb-0">Sponsor list</h5></div>
            <div class="card-body-p">
                @forelse ($sponsors as $s)
                    <div class="info-row">
                        <span class="sp-thumb">
                            @if ($s->logo_path)<img src="{{ asset($s->logo_path) }}" alt="">@else<i class="fa-solid fa-building"></i>@endif
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value text-break" style="font-size:.92rem;">{{ $s->name }}</div>
                            <div class="cell-sub text-break">{{ $s->website ?: 'No website' }}</div>
                        </div>
                        <span class="badge-soft badge-{{ $s->active ? 'success' : 'muted' }}">{{ $s->active ? 'Shown' : 'Hidden' }}</span>
                        <form method="post" action="{{ route('admin.sponsors.toggle', $s->id) }}">@csrf
                            <button class="btn btn-ghost btn-sm2" type="submit">{{ $s->active ? 'Hide' : 'Show' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.sponsors.destroy', $s->id) }}">@csrf
                            <button class="btn btn-ghost btn-sm2" type="submit" title="Remove"
                                    data-confirm-title="Remove {{ $s->name }}?" data-confirm-text="It disappears from the landing page and its logo file is deleted."
                                    data-confirm-icon="warning" data-confirm-yes="Remove"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="text-soft mb-0" style="font-size:.88rem;">No sponsors added yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card-soft fade-up d3 mt-4">
    <div class="card-head">
        <div><h5 class="mb-0">Sponsor enquiries</h5><span class="cell-sub">Sent from “Want to sponsor SIGLA 2026?” on the landing page. Reply by email.</span></div>
    </div>
    <div class="card-body-p">
        @forelse ($inquiries as $q)
            <div class="review-card {{ $q->status === 'new' ? 'needs-look' : '' }}">
                <span class="review-icon"><i class="fa-solid fa-building"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="cell-strong">{{ $q->company }}</span>
                        <span class="badge-soft badge-{{ ['new' => 'gold', 'contacted' => 'info', 'closed' => 'muted'][$q->status] ?? 'muted' }}">{{ ucfirst($q->status) }}</span>
                        <span class="cell-sub">{{ pretty_date($q->created_at) }}</span>
                    </div>
                    <div class="cell-sub mt-1">
                        {{ $q->contact_name }} ·
                        <a href="mailto:{{ $q->email }}?subject={{ rawurlencode('Re: SIGLA 2026 sponsorship') }}">{{ $q->email }}</a>
                        @if ($q->phone) · {{ $q->phone }}@endif
                    </div>
                    @if ($q->message)<div class="mt-2" style="font-size:.88rem;white-space:pre-line;">{{ $q->message }}</div>@endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (['contacted' => 'Mark contacted', 'closed' => 'Close', 'new' => 'Reopen'] as $st => $lbl)
                        @continue($q->status === $st || ($st === 'new' && $q->status !== 'closed'))
                        <form method="post" action="{{ route('admin.sponsors.inquiry', $q->id) }}">@csrf
                            <input type="hidden" name="status" value="{{ $st }}">
                            <button class="btn btn-ghost btn-sm2" type="submit">{{ $lbl }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-soft mb-0" style="font-size:.88rem;">No messages yet.</p>
        @endforelse
    </div>
</div>
@endsection
