@extends('layouts.app')
@section('title', 'Change Requests')
@section('crumb', 'Administration')
@section('nav', 'requests')

@section('content')
<div class="page-hero fade-up">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="eyebrow on-dark"><i class="fa-solid fa-id-card-clip"></i> Identity changes</span>
            <h2>Change Requests</h2>
            <p>Students cannot edit their SR-code or GSuite email themselves. They ask here, with a reason. Check it, then approve or return it.
               A new email has to be confirmed again by the student.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <span class="badge-soft badge-gold" style="font-size:.82rem;"><i class="fa-solid fa-inbox"></i> {{ $pending->count() }} pending</span>
        </div>
    </div>
</div>

<div class="card-soft fade-up d2 mb-4">
    <div class="card-head"><h5 class="mb-0">Waiting for a decision</h5></div>
    <div class="card-body-p">
        @forelse ($pending as $r)
            <div class="review-card">
                <span class="review-icon"><i class="fa-solid {{ $r->field === 'email' ? 'fa-envelope' : 'fa-id-card' }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge-soft badge-gold">{{ $r->label() }}</span>
                        <a class="cell-strong text-decoration-none" href="{{ route('admin.documents.student', $r->user_id) }}">{{ $r->user->name }}</a>
                        <span class="cell-sub">{{ $r->user->college_code }} · requested {{ pretty_date($r->created_at) }}</span>
                    </div>
                    <div class="mt-2" style="font-size:.9rem;">
                        <span class="text-soft text-break">{{ $r->old_value }}</span>
                        <i class="fa-solid fa-arrow-right mx-2"></i>
                        <strong class="text-break">{{ $r->new_value }}</strong>
                    </div>
                    <div class="cell-sub mt-1"><i class="fa-solid fa-comment me-1"></i>“{{ $r->reason }}”</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="{{ route('admin.requests.decide', $r->id) }}" class="d-inline">
                        @csrf <input type="hidden" name="action" value="approve">
                        <button class="btn btn-green btn-sm2" type="submit"
                                data-confirm-title="Approve this {{ $r->label() }} change?"
                                data-confirm-text="{{ $r->user->name }}'s {{ $r->label() }} becomes {{ $r->new_value }}.{{ $r->field === 'email' ? ' They must confirm the new email before they can continue.' : '' }}"
                                data-confirm-icon="question" data-confirm-yes="Approve">
                            <i class="fa-solid fa-circle-check"></i> Approve
                        </button>
                    </form>
                    <button class="btn btn-ghost btn-sm2" type="button" data-bs-toggle="modal" data-bs-target="#ret{{ $r->id }}"><i class="fa-solid fa-rotate-left"></i> Return</button>
                </div>
            </div>

            <div class="modal fade" id="ret{{ $r->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="post" action="{{ route('admin.requests.decide', $r->id) }}" class="modal-content" style="border:0;border-radius:20px;">
                        @csrf <input type="hidden" name="action" value="reject">
                        <div class="modal-header border-0"><h5 class="modal-title">Return this request</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body pt-0">
                            <label class="form-label-sm" for="note{{ $r->id }}">Note to the student *</label>
                            <textarea class="form-control" id="note{{ $r->id }}" name="note" rows="3" required minlength="5" placeholder="e.g. Please bring your school ID to the SSC office first."></textarea>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-ghost btn-sm2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-gold btn-sm2">Return request</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <span class="stat-icon green d-inline-grid mb-3" style="width:56px;height:56px;font-size:1.3rem;"><i class="fa-solid fa-check"></i></span>
                <h5 class="mb-1">No pending requests</h5>
                <p class="text-soft mb-0" style="font-size:.88rem;">Nobody is waiting for an SR-code or email change.</p>
            </div>
        @endforelse
    </div>
</div>

<div class="card-soft fade-up d3">
    <div class="card-head"><div><h5 class="mb-0">Recent decisions</h5><span class="cell-sub">Also written to the audit trail</span></div></div>
    <div class="table-responsive">
        <table class="table table-stack" style="width:100%;">
            <thead><tr><th>Decided</th><th>Student</th><th>Change</th><th>Decision</th><th>Note</th></tr></thead>
            <tbody>
                @forelse ($decided as $r)
                    <tr>
                        <td data-label="Decided" class="cell-sub">{{ pretty_date($r->decided_at) }}</td>
                        <td data-label="Student" class="cell-strong">{{ $r->user->name }}</td>
                        <td data-label="Change"><span class="cell-sub">{{ $r->label() }}:</span> {{ $r->old_value }} → <strong>{{ $r->new_value }}</strong></td>
                        <td data-label="Decision">{!! status_badge($r->status) !!}</td>
                        <td data-label="Note" class="cell-sub">{{ $r->decision_note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="cell-sub text-center py-4">Nothing decided yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
