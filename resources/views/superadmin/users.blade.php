@extends('superadmin.layout')
@section('title', 'Users')
@section('nav', 'users')
@section('heading', 'Users')
@section('sub', number_format($total) . ' accounts across every role.')
@section('actions')
    <button type="button" class="sa-btn primary" data-bs-toggle="modal" data-bs-target="#addModal" id="add"><i class="fa-solid fa-user-plus"></i> Add Account</button>
@endsection

@section('content')
<div class="sa-pills">
    <a href="{{ route('superadmin.users') }}" class="sa-pill {{ request('role') ? '' : 'is-on' }}">All<b>{{ number_format($total) }}</b></a>
    @foreach ($roles as $key => $r)
        <a href="{{ route('superadmin.users', ['role' => $key]) }}" class="sa-pill {{ request('role') === $key ? 'is-on' : '' }}">{{ $r['label'] }}<b>{{ number_format($r['count']) }}</b></a>
    @endforeach
</div>

<div class="sa-card">
    <form method="get" class="sa-filter">
        <input type="search" class="form-control grow" name="q" value="{{ request('q') }}" placeholder="Search name, email or ID number…">
        <select class="form-select" name="role" style="width:auto;">
            <option value="">All Roles</option>
            @foreach ($roles as $key => $r)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $r['label'] }}</option>@endforeach
        </select>
        <select class="form-select" name="status" style="width:auto;">
            <option value="">Any Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="disabled" @selected(request('status') === 'disabled')>Deactivated</option>
        </select>
        <button class="sa-btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="sa-scroll"><table class="sa-table">
        <thead><tr><th>Account</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th><th></th></tr></thead>
        <tbody>
            @forelse ($users as $u)
                @php
                    $g = \App\Http\Controllers\SuperAdmin\UserController::groupOf($u);
                    $label = $g === 'employee' ? (config('ereserve.employee_types')[$u->member_type] ?? 'Faculty / Staff') : $roles[$g]['label'];
                @endphp
                <tr>
                    <td><div class="sa-user"><span class="sa-avatar">{{ $u->initials ?: strtoupper(mb_substr($u->name, 0, 2)) }}</span>
                        <div><b>{{ $u->name }}</b><small>{{ $u->email }}@if ($u->student_no) · {{ $u->student_no }}@endif</small></div></div></td>
                    <td><span class="sa-chip {{ $roles[$g]['tone'] }}">{{ $label }}</span></td>
                    <td><span class="sa-chip {{ $u->isActive() ? 'success' : 'muted' }}">{{ $u->isActive() ? 'Active' : 'Deactivated' }}</span></td>
                    <td>{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}</td>
                    <td>{{ $u->created_at?->format('M j, Y') }}</td>
                    <td class="text-end text-nowrap">
                        @if ($u->id !== auth()->id())
                            <form method="post" action="{{ route('superadmin.users.reset', $u->id) }}" class="d-inline">@csrf
                                <button class="sa-btn sm" type="submit" title="Reset password" aria-label="Reset password for {{ $u->name }}"
                                        data-confirm-title="Reset {{ $u->name }}’s password?" data-confirm-text="A new temporary password is shown once and the old one stops working."
                                        data-confirm-icon="warning" data-confirm-yes="Reset"><i class="fa-solid fa-key"></i></button>
                            </form>
                            <form method="post" action="{{ route('superadmin.users.toggle', $u->id) }}" class="d-inline">@csrf
                                <button class="sa-btn sm {{ $u->isActive() ? 'danger' : 'ok' }}" type="submit"
                                        @if ($u->isActive()) data-confirm-title="Deactivate {{ $u->name }}?" data-confirm-text="They are signed out and cannot sign in until you reactivate them." data-confirm-icon="warning" data-confirm-yes="Deactivate" @endif>
                                    {{ $u->isActive() ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @else
                            <span class="sa-chip muted">You</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="sa-empty">No accounts match.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="mt-3">{{ $users->links() }}</div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="{{ route('superadmin.users.store') }}" class="modal-content" style="border:0;border-radius:20px;" autocomplete="off">
            @csrf
            <div class="modal-header border-0"><h5 class="modal-title">Add account</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body pt-0">
                <label class="form-label small fw-bold" for="ad-kind">Kind of account</label>
                <select class="form-select" id="ad-kind" name="kind" required>
                    <option value="">Choose…</option>
                    @foreach ($kinds as $k => [$kl])<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $kl }}</option>@endforeach
                </select>

                <label class="form-label small fw-bold mt-3" for="ad-name">Full name</label>
                <input type="text" class="form-control" id="ad-name" name="name" value="{{ old('name') }}" maxlength="160" required>

                <label class="form-label small fw-bold mt-3" for="ad-email">Email</label>
                <input type="email" class="form-control" id="ad-email" name="email" value="{{ old('email') }}" maxlength="190" required>
                <div class="form-text">SSC staff, students and faculty need a university address. Super admins and sponsors can use any email.</div>

                <div id="ad-idbox" class="d-none">
                    <label class="form-label small fw-bold mt-3" for="ad-id" id="ad-idlabel">ID number</label>
                    <input type="text" class="form-control" id="ad-id" name="id_no" value="{{ old('id_no') }}" maxlength="32">
                </div>

                <label class="form-label small fw-bold mt-3" for="ad-pass">Temporary password <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" id="ad-pass" name="password" value="" maxlength="200" autocomplete="off">
                <div class="form-text">Leave empty to generate one. It is shown once, and the person must choose their own at first sign-in.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="sa-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="sa-btn primary"><i class="fa-solid fa-user-plus"></i> Create account</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var kind = document.getElementById('ad-kind'), box = document.getElementById('ad-idbox'), lab = document.getElementById('ad-idlabel'), inp = document.getElementById('ad-id');
        function sync() {
            var k = kind.value, student = k === 'student', emp = ['faculty', 'instructor', 'staff'].indexOf(k) > -1;
            box.classList.toggle('d-none', !(student || emp));
            inp.required = student;
            lab.textContent = student ? 'SR-code (e.g. 24-12345)' : 'Employee ID (optional)';
        }
        kind.addEventListener('change', sync); sync();
        @if (session('open_add') || old('kind'))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).show();
        @endif
        if (location.hash === '#add') { bootstrap.Modal.getOrCreateInstance(document.getElementById('addModal')).show(); }
    });
</script>
@endsection
