@extends('layouts.app')
@section('title', $user['name'])
@section('crumb', 'Review Documents')
@section('nav', 'documents')

<?php
    $types   = \App\Support\DocumentSets::metaFor($user['member_type'] ?? 'student');
    $parse   = fn ($d) => $d ? (is_string($d['auto_checks'] ?? null) ? (json_decode($d['auto_checks'], true) ?: []) : ($d['auto_checks'] ?? [])) : [];
    $waiting = array_filter($docs, fn ($d) => $d && $d['status'] === 'submitted');
    $okIds   = array_filter($waiting, fn ($d) => !\App\Services\DocumentChecker::needsLook($parse($d)));
    $counts  = ['approved' => 0, 'submitted' => 0, 'rejected' => 0, 'none' => 0];
    foreach ($docs as $d) { $counts[$d['status'] ?? 'none']++; }
    $back    = route('admin.documents', array_filter(['college' => $college]));
?>

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-user-graduate"></i> Student review</span>
            <h2>{{ $user['name'] }}</h2>
            <p>{{ $user['student_no'] }} · {{ $user['program'] }}@if ($user['year_level']) · {{ $user['year_level'] }}@endif @if ($user['college_code']) · {{ $user['college_code'] }}@endif · {{ $user['email'] }}</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <span class="badge-soft badge-gold"><i class="fa-solid fa-circle-check"></i> {{ $counts['approved'] }}/{{ count($types) }} approved</span>
                @if ($ticket)<span class="badge-soft badge-gold"><i class="fa-solid fa-ticket"></i> {{ $ticket['ticket_no'] }}</span>@endif
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <a href="{{ $back }}" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-arrow-left"></i> Back to the queue</a>
    <div class="d-flex flex-wrap gap-2">
        @if (count($okIds) > 0)
            <form method="post" action="{{ route('admin.documents.bulk') }}" class="d-inline">
                @csrf
                @foreach ($okIds as $d)
                    <input type="hidden" name="ids[]" value="{{ (int) $d['id'] }}">
                    <input type="hidden" name="versions[{{ (int) $d['id'] }}]" value="{{ strtotime((string) $d['uploaded_at']) }}">
                @endforeach
                <button type="submit" class="btn btn-green btn-sm2"
                        data-confirm-title="Approve {{ count($okIds) }} document{{ count($okIds) === 1 ? '' : 's' }} for {{ $user['name'] }}?"
                        data-confirm-text="Only approve what you have opened and checked below. Approved files are locked and cannot be replaced by the student."
                        data-confirm-icon="question" data-confirm-yes="Approve">
                    <i class="fa-solid fa-check-double"></i> Approve all waiting ({{ count($okIds) }})
                </button>
            </form>
        @endif
        @if ($next)
            <a href="{{ route('admin.documents.student', array_filter(['user' => $next, 'college' => $college])) }}" class="btn btn-gold btn-sm2">
                Next student <i class="fa-solid fa-arrow-right"></i>
            </a>
        @endif
    </div>
</div>

@if (count($waiting) > count($okIds))
    <div class="callout callout-warning mb-3" style="font-size:.86rem;">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        The automatic checks flagged {{ count($waiting) - count($okIds) }} file{{ count($waiting) - count($okIds) === 1 ? '' : 's' }}. Those are never approved in one click: open each one and decide by hand.
    </div>
@endif

<div class="row g-3">
    @foreach ($types as $key => $m)
        <?php $d = $docs[$key]; $st = $d['status'] ?? 'none'; $checks = $parse($d); $look = $d && \App\Services\DocumentChecker::needsLook($checks); $modalId = 'rej' . (int) ($d['id'] ?? 0); ?>
        <div class="col-12 col-xl-4">
            <div class="review-card review-doc {{ $look ? 'needs-look' : '' }} h-100">
                <div class="d-flex align-items-center gap-2 w-100">
                    <span class="review-icon"><i class="fa-solid {{ $m['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="cell-strong">{{ $m['label'] }}</div>
                        <div class="cell-sub">
                            @if ($d) {{ number_format($d['size_bytes'] / 1024) }} KB · uploaded {{ pretty_date($d['uploaded_at']) }}
                            @else Not uploaded yet @endif
                        </div>
                    </div>
                    @if ($d){!! status_badge($d['status']) !!}@endif
                </div>

                @if ($d)
                    @if ($checks)
                        <div class="auto-checks w-100">
                            @foreach ($checks as $c)
                                <span class="chk chk-{{ $c['s'] }}">
                                    <i class="fa-solid {{ ['ok' => 'fa-circle-check', 'warn' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'][$c['s']] ?? 'fa-circle-info' }}"></i>{{ $c['t'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($st === 'rejected' && $d['review_note'])
                        <div class="cell-sub w-100"><i class="fa-solid fa-comment me-1"></i>Returned{{ !empty($d['reviewer']) ? ' by ' . $d['reviewer'] : '' }}: “{{ $d['review_note'] }}”</div>
                    @elseif ($st === 'approved')
                        <div class="cell-sub w-100"><i class="fa-solid fa-circle-check me-1"></i>Approved{{ !empty($d['reviewer']) ? ' by ' . $d['reviewer'] : '' }} · {{ pretty_date($d['reviewed_at']) }}</div>
                    @endif

                    <div class="doc-preview w-100">
                        <button type="button" class="btn btn-outline-green btn-sm2 js-preview" data-src="{{ document_url((int) $d['id']) }}">
                            <i class="fa-solid fa-eye"></i> View here
                        </button>
                        <a href="{{ document_url((int) $d['id']) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm2">
                            <i class="fa-solid fa-up-right-from-square"></i> New tab
                        </a>
                        <div class="doc-frame d-none"><iframe title="{{ $m['label'] }}" loading="lazy"></iframe></div>
                    </div>

                    @if ($st === 'submitted')
                        <div class="d-flex flex-wrap gap-2 w-100 mt-auto">
                            <form method="post" action="{{ route('admin.documents.review') }}" class="d-inline flex-grow-1">
                                @csrf
                                <input type="hidden" name="document_id" value="{{ (int) $d['id'] }}">
                                <input type="hidden" name="version" value="{{ strtotime((string) $d['uploaded_at']) }}">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-green btn-sm2 w-100" type="submit"
                                        data-confirm-title="Approve this {{ $m['short'] }}?"
                                        data-confirm-text="You are confirming it is a valid {{ strtolower($m['label']) }} for {{ $user['name'] }}. The student can no longer replace it."
                                        data-confirm-icon="question" data-confirm-yes="Approve">
                                    <i class="fa-solid fa-circle-check"></i> Approve
                                </button>
                            </form>
                            <button class="btn btn-ghost btn-sm2 flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                <i class="fa-solid fa-rotate-left"></i> Return
                            </button>
                        </div>

                        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="post" action="{{ route('admin.documents.review') }}" class="modal-content" style="border:0;border-radius:20px;">
                                    @csrf
                                    <input type="hidden" name="document_id" value="{{ (int) $d['id'] }}">
                                    <input type="hidden" name="version" value="{{ strtotime((string) $d['uploaded_at']) }}">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title">Return this {{ $m['short'] }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body pt-0">
                                        <p class="text-soft" style="font-size:.87rem;">
                                            {{ $user['name'] }}’s ticket is paused until they upload a corrected PDF. Tell them exactly what to fix.
                                        </p>
                                        <label class="form-label-sm" for="reason{{ (int) $d['id'] }}">Note to the student *</label>
                                        <textarea class="form-control" id="reason{{ (int) $d['id'] }}" name="reason" rows="3" required minlength="5"
                                                  placeholder="e.g. The waiver is missing your signature."></textarea>
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            @foreach ([
                                                'The file is blurry or unreadable. Please rescan it.',
                                                'The document is not signed.',
                                                'This is not for the current semester.',
                                                'Your name or SR-code is cropped out.',
                                            ] as $preset)
                                                <button type="button" class="btn btn-ghost btn-sm2 js-preset"
                                                        data-target="reason{{ (int) $d['id'] }}" data-text="{{ $preset }}">{{ $preset }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-ghost btn-sm2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-gold btn-sm2">Return document</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
</div>

@if (!$waiting)
    <div class="card-soft mt-3 text-center py-4">
        <h5 class="mb-1">Nothing left to review for {{ $user['name'] }}</h5>
        <p class="text-soft mb-3" style="font-size:.88rem;">Every uploaded document has a decision.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="{{ $back }}" class="btn btn-ghost btn-sm2">Back to the queue</a>
            @if ($next)<a href="{{ route('admin.documents.student', array_filter(['user' => $next, 'college' => $college])) }}" class="btn btn-gold btn-sm2">Next student <i class="fa-solid fa-arrow-right"></i></a>@endif
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    /* Load the PDF only when asked, so just looking at the page does not open every file. */
    document.querySelectorAll('.js-preview').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.parentElement.querySelector('.doc-frame'), f = wrap.querySelector('iframe');
            var open = wrap.classList.toggle('d-none') === false;
            if (open && !f.getAttribute('src')) f.setAttribute('src', btn.dataset.src);
            btn.innerHTML = open ? '<i class="fa-solid fa-eye-slash"></i> Hide' : '<i class="fa-solid fa-eye"></i> View here';
        });
    });
    document.querySelectorAll('.js-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById(btn.dataset.target);
            if (box) { box.value = btn.dataset.text; box.focus(); }
        });
    });
</script>
@endpush
