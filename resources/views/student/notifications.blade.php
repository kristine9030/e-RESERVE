@extends('layouts.app')
@section('title', 'Notifications')
@section('crumb', 'Student Portal')
@section('nav', 'notifications')

<?php $icons = ['submitted' => 'fa-paper-plane', 'approved' => 'fa-circle-check', 'returned' => 'fa-rotate-left', 'ticket' => 'fa-ticket', 'waitlist' => 'fa-hourglass-half'];
      $cta   = ['submitted' => 'Track my status', 'approved' => 'Track my status', 'returned' => 'Fix my documents', 'ticket' => 'Open my ticket', 'waitlist' => 'Check my status']; ?>

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-bell"></i> Updates from SSC</span>
            <h2>Notifications</h2>
            <p>Every decision on your documents and your ticket shows up here. Tap a notification to read it in full.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;"><i class="fa-solid fa-envelope-open-text"></i> {{ $unread }} unread</span>
        </div>
    </div>
</div>

<div class="card-soft fade-up d2">
    <div class="card-head">
        <div class="btn-group" role="group" aria-label="Filter">
            <a href="{{ route('notifications.index') }}" class="btn btn-sm2 {{ $only ? 'btn-ghost' : 'btn-green' }}">All</a>
            <a href="{{ route('notifications.index', ['show' => 'unread']) }}" class="btn btn-sm2 {{ $only ? 'btn-green' : 'btn-ghost' }}">Unread</a>
        </div>
        @if ($unread)
            <form method="post" action="{{ route('notifications.read') }}">@csrf
                <button type="submit" class="btn btn-ghost btn-sm2"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="card-body-p p-0">
        @forelse ($notices as $n)
            <button type="button" class="notice-row {{ $n->read_at ? '' : 'unread' }}" data-bs-toggle="modal" data-bs-target="#noticeModal"
                    data-id="{{ $n->id }}" data-type="{{ $n->type }}" data-title="{{ $n->title }}" data-body="{{ $n->body }}"
                    data-time="{{ $n->created_at->format('F j, Y · g:i A') }}" data-ago="{{ $n->created_at->diffForHumans() }}"
                    data-url="{{ $n->url ?: route('tracking') }}" data-cta="{{ $cta[$n->type] ?? 'Open' }}" data-seen="{{ route('notifications.seen', $n->id) }}">
                <span class="bell-ico t-{{ $n->type }}"><i class="fa-solid {{ $icons[$n->type] ?? 'fa-bell' }}"></i></span>
                <span class="bell-txt flex-grow-1">
                    <span class="bell-title">{{ $n->title }}</span>
                    @if ($n->body)<span class="bell-body">{{ \Illuminate\Support\Str::limit($n->body, 110) }}</span>@endif
                    <span class="bell-time">{{ $n->created_at->diffForHumans() }}</span>
                </span>
                <i class="fa-solid fa-chevron-right notice-go"></i>
            </button>
        @empty
            <div class="bell-empty py-5"><i class="fa-regular fa-bell"></i>{{ $only ? 'No unread notifications.' : 'Nothing yet. Updates from SSC show up here.' }}</div>
        @endforelse
    </div>

    @if ($notices->hasPages())
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="cell-sub">Showing {{ $notices->firstItem() }}–{{ $notices->lastItem() }} of {{ $notices->total() }}</span>
            {{ $notices->links() }}
        </div>
    @endif
</div>

<!-- ================= One notification, opened ================= -->
<div class="modal fade" id="noticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content notice-modal" id="nmCard">
            <div class="nm-head">
                <span class="bell-ico notice-big" id="nmIcon"><i class="fa-solid fa-bell"></i></span>
                <div class="min-w-0">
                    <span class="nm-kind" id="nmKind">Update</span>
                    <div class="nm-time"><i class="fa-regular fa-clock"></i> <span id="nmAgo"></span></div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="nm-body">
                <h5 id="nmTitle"></h5>
                <p id="nmBody"></p>
                <div class="nm-date"><i class="fa-regular fa-calendar"></i> <span id="nmTime"></span></div>
            </div>
            <div class="nm-foot">
                <button type="button" class="btn btn-ghost btn-sm2" data-bs-dismiss="modal">Close</button>
                <a href="#" id="nmGo" class="btn btn-gold btn-sm2"></a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var icons = {submitted: 'fa-paper-plane', approved: 'fa-circle-check', returned: 'fa-rotate-left', ticket: 'fa-ticket', waitlist: 'fa-hourglass-half'};
        var KINDS = {submitted: 'Documents', approved: 'Approved', returned: 'Action needed', ticket: 'Your ticket', waitlist: 'Waiting list'};
        var modal = document.getElementById('noticeModal');
        var token = document.querySelector('meta[name="csrf-token"]').content;

        function fill(row) {
            var d = row.dataset;
            document.getElementById('nmTitle').textContent = d.title;
            document.getElementById('nmBody').textContent = d.body || '';
            document.getElementById('nmTime').textContent = d.time;
            document.getElementById('nmAgo').textContent = d.ago;
            var go = document.getElementById('nmGo'); go.href = d.url; go.textContent = d.cta;
            var ic = document.getElementById('nmIcon'); ic.className = 'bell-ico notice-big t-' + d.type;
            document.getElementById('nmCard').className = 'modal-content notice-modal t-' + d.type;
            document.getElementById('nmKind').textContent = KINDS[d.type] || 'Update';
            ic.innerHTML = '<i class="fa-solid ' + (icons[d.type] || 'fa-bell') + '"></i>';
            if (row.classList.contains('unread')) {          /* opening it = reading it */
                row.classList.remove('unread');
                fetch(d.seen, {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'}).catch(function () {});
                var c = document.querySelector('.bell-count');
                if (c) { var n = parseInt(c.textContent, 10); if (n > 1) { c.textContent = n - 1; } else { c.remove(); } }
            }
        }
        modal.addEventListener('show.bs.modal', function (e) { fill(e.relatedTarget); });

        var want = {{ (int) $open }};                          /* arrived from the bell: open that one straight away */
        if (want) {
            var row = document.querySelector('.notice-row[data-id="' + want + '"]');
            if (row) { fill(row); bootstrap.Modal.getOrCreateInstance(modal).show(); }
        }
    })();
</script>
@endpush
