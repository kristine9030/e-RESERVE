@extends('layouts.app')
@section('title', 'My Profile')
@section('crumb', 'Student Portal')
@section('nav', 'profile')

@section('content')
<?php $changeRequests = \App\Models\ChangeRequest::where('user_id', auth()->id())->orderByDesc('id')->limit(6)->get();
      $pendingFields = array_fill_keys($changeRequests->where('status', 'pending')->pluck('field')->all(), true); ?>
<div class="row g-4">

    <!-- ================= Profile card ================= -->
    <div class="col-lg-5">
        <div class="card-soft overflow-hidden fade-up">
            <div class="profile-hero"></div>

            <div class="card-body-p text-center">
                <div class="profile-avatar mx-auto">{{ $user['initials'] }}</div>

                <h4 class="mt-3 mb-1" style="font-size:1.25rem;">{{ $user['name'] }}</h4>
                <p class="text-soft mb-2" style="font-size:.85rem;">{{ $user['email'] }}</p>

                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    @if (!empty($user['is_employee']))
                        <span class="badge-soft badge-success"><i class="fa-solid fa-circle-check"></i> {{ auth()->user()->hasVerifiedEmail() ? 'Verified Employee' : 'Email not confirmed' }}</span>
                        <span class="badge-soft badge-gold"><i class="fa-solid fa-briefcase"></i> {{ $user['program'] }}</span>
                    @else
                    <span class="badge-soft badge-success"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                    <span class="badge-soft badge-gold"><i class="fa-solid fa-graduation-cap"></i> {{ $user['year'] }}</span>
                    @endif
                </div>

                <button class="btn btn-gold btn-block2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                </button>
                <a href="{{ route('account.password') }}" class="btn btn-ghost btn-block2 mt-2">
                    <i class="fa-solid fa-key"></i> Change Password
                </a>
            </div>
        </div>

        <div class="card-soft mt-4 fade-up d2">
            <div class="card-head"><h6 class="mb-0">My SIGLA 2026 Status</h6></div>
            <div class="card-body-p">
                @if (empty($user['is_employee']))
                <div class="receipt-row">
                    <span class="k">Documents</span>
                    <span class="v">{{ $progress['uploaded'] }} of {{ $progress['total'] }} uploaded</span>
                </div>
                @endif
                <div class="receipt-row">
                    <span class="k">Ticket</span>
                    <span class="v">{!! $ticket ? '<span class="mono">' . e($ticket['ticket_no']) . '</span>' : '<span class="badge-soft badge-muted">Not issued</span>' !!}</span>
                </div>
                @if (empty($user['is_employee']))
                <a href="{{ route('tracking') }}" class="btn btn-outline-green btn-block2 btn-sm2 mt-3">
                    <i class="fa-solid fa-route"></i> Track My Progress
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- ================= Details ================= -->
    <div class="col-lg-7">
        <div class="card-soft fade-up d1">
            <div class="card-head">
                <div>
                    <h5 class="mb-0">{{ !empty($user['is_employee']) ? 'Employee' : 'Student' }} Information</h5>
                    <span class="cell-sub">Edit most details yourself. SR-code and email need SSC's approval.</span>
                </div>
                <button class="btn btn-outline-green btn-sm2" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
            </div>

            <div class="card-body-p">
                <?php $me = auth()->user(); $emp = $me->isEmployee();
                $collegeName = $me->college_code ? $me->college_code . ' — ' . (config('ereserve.colleges')[$me->college_code] ?? '') : 'Not set';
                $fields = [
                    ['fa-user',            'Student Name',   $user['name']],
                    ['fa-id-card',         'SR-Code', $user['student_no'], 'student_no'],
                    ['fa-envelope',        'GSuite Email',   $user['email'], 'email'],
                    ['fa-graduation-cap',  'Program',        $user['program']],
                    ['fa-layer-group',     'Year Level',     $user['year']],
                    ['fa-building-columns','College',        $collegeName],
                    ['fa-phone',           'Contact Number', $user['contact']],
                ];
                if ($emp) {
                    $fields = [
                        ['fa-user',     'Name',        $user['name']],
                        ['fa-id-card',  'Employee ID', $user['student_no'], 'student_no'],
                        ['fa-envelope', 'Email',       $user['email'], 'email'],
                        ['fa-briefcase', 'Category',   $user['program']],
                        ['fa-phone',    'Contact Number', $user['contact']],
                    ];
                }
                foreach ($fields as $fld): [$icon, $label, $value] = $fld; $req = $fld[3] ?? null; ?>
                    <div class="info-row">
                        <span class="info-icon"><i class="fa-solid {{ $icon }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-label">{{ $label }}</div>
                            <div class="info-value text-break">{{ $value }}</div>
                        </div>
                        @if ($req)
                            @if ($pendingFields[$req] ?? false)
                                <span class="badge-soft badge-gold"><i class="fa-solid fa-hourglass-half"></i> Request pending</span>
                            @else
                                <button type="button" class="btn btn-ghost btn-sm2 js-req" data-field="{{ $req }}" data-label="{{ $label }}" data-current="{{ $value }}"
                                        data-bs-toggle="modal" data-bs-target="#changeReqModal"><i class="fa-solid fa-paper-plane"></i> Request change</button>
                            @endif
                        @endif
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        @if ($changeRequests->isNotEmpty())
        <div class="card-soft mt-4 fade-up d2">
            <div class="card-head"><div><h6 class="mb-0">My change requests</h6><span class="cell-sub">SSC reviews every SR-code or email change</span></div></div>
            <div class="card-body-p">
                @foreach ($changeRequests as $cr)
                    <div class="info-row">
                        <span class="info-icon"><i class="fa-solid {{ $cr->field === 'email' ? 'fa-envelope' : 'fa-id-card' }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value text-break" style="font-size:.88rem;">{{ $cr->label() }}: {{ $cr->new_value }}</div>
                            <div class="cell-sub text-break">Sent {{ pretty_date($cr->created_at) }}@if ($cr->decision_note) · “{{ $cr->decision_note }}”@endif</div>
                        </div>
                        {!! status_badge($cr->status) !!}
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="card-soft mt-4 fade-up d3">
            <div class="card-head"><h6 class="mb-0">Account Activity</h6></div>
            <div class="card-body-p">
                <?php $activity = [];
                if ($ticket) {
                    $activity[] = ['fa-ticket', 'Ticket number issued', $ticket['ticket_no'], $ticket['issued_at'], 'success'];
                }
                foreach ($docs as $d) {
                    $activity[] = ['fa-file-pdf', config('ereserve.documents')[$d['type']]['label'] . ' uploaded', $d['name'], $d['uploaded_at'], 'info'];
                }
                $activity[] = ['fa-right-to-bracket', 'Signed in to e-RESERVE', $user['email'], $signedInAt, 'muted'];

                foreach ($activity as [$icon, $title, $meta, $when, $tone]): ?>
                    <div class="info-row">
                        <span class="info-icon" style="{{ $tone === 'success' ? 'background:rgba(31,157,99,.12);color:var(--success);' : '' }}">
                            <i class="fa-solid {{ $icon }}"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="info-value" style="font-size:.88rem;">{{ $title }}</div>
                            <div class="cell-sub text-break">{{ $meta }}</div>
                        </div>
                        <span class="cell-sub text-nowrap">{{ pretty_date($when, 'M d · g:i A') }}</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= Edit Profile modal ================= -->
<?php $me = auth()->user();
      $nameLocked = \App\Models\Document::where('user_id', $me->id)->where('status', 'approved')->exists();
      $curCollege = old('college', $me->college_code); ?>
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Edit Profile</h5>
                    <span class="cell-sub">SR-code and GSuite email cannot be changed.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="post" action="{{ route('profile.update') }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label-sm d-block" for="pf-name">Full name</label>
                    <input type="text" class="form-control" id="pf-name" name="name" value="{{ old('name', $me->name) }}" maxlength="160" required {{ $nameLocked ? 'readonly' : '' }}>
                    @if ($nameLocked)<div class="cell-sub mt-1"><i class="fa-solid fa-lock"></i> Locked: SSC already approved one of your documents.</div>@endif

                    @if ($emp)
                    <label class="form-label-sm d-block mt-3" for="pf-type">Category</label>
                    <select class="form-select" id="pf-type" name="member_type" required>
                        @foreach (config('ereserve.employee_types') as $k => $lbl)
                            <option value="{{ $k }}" @selected(old('member_type', $me->member_type) === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @else
                    <label class="form-label-sm d-block mt-3" for="pf-college">College</label>
                    <select class="form-select" id="pf-college" name="college" required>
                        @foreach (config('ereserve.colleges') as $code => $label)
                            <option value="{{ $code }}" @selected($curCollege === $code)>{{ $code }} — {{ $label }}</option>
                        @endforeach
                    </select>

                    <label class="form-label-sm d-block mt-3" for="pf-program">Programme</label>
                    <select class="form-select" id="pf-program" name="program" required></select>

                    <label class="form-label-sm d-block mt-3" for="pf-year">Year level</label>
                    <select class="form-select" id="pf-year" name="year_level" required>
                        @foreach (config('ereserve.year_levels') as $y)
                            <option value="{{ $y }}" @selected(old('year_level', $me->year_level) === $y)>{{ $y }}</option>
                        @endforeach
                    </select>

                    @endif

                    <label class="form-label-sm d-block mt-3" for="mobile">Contact number</label>
                    <input type="text" class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $user['contact']) }}"
                           placeholder="09171234567" inputmode="numeric" maxlength="13">
                    <div class="cell-sub mt-1">Optional. Format: 09XXXXXXXXX.</div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= Request a change (SR-code / email) ================= -->
<div class="modal fade" id="changeReqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Request a change: <span id="crLabel"></span></h5>
                    <span class="cell-sub">SSC reviews it first. Nothing changes until it is approved.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="{{ route('profile.change') }}">
                @csrf
                <input type="hidden" name="field" id="crField" value="{{ old('field') }}">
                <div class="modal-body">
                    <div class="cell-sub mb-2">Now: <strong id="crCurrent" class="text-break"></strong></div>
                    <label class="form-label-sm d-block" for="crNew">New <span id="crLabel2"></span></label>
                    <input type="text" class="form-control" id="crNew" name="new_value" value="{{ old('new_value') }}" maxlength="190" required autocomplete="off">
                    <div class="cell-sub mt-1" id="crHint"></div>

                    <label class="form-label-sm d-block mt-3" for="crReason">Why does it need to change?</label>
                    <textarea class="form-control" id="crReason" name="reason" rows="3" minlength="10" maxlength="300" required
                              placeholder="e.g. I typed my SR-code wrong when I registered.">{{ old('reason') }}</textarea>
                    <div class="cell-sub mt-2" id="crNote"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> Send to SSC</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var PROGRAMS = {!! json_encode(config('ereserve.programs'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
        var college = document.getElementById('pf-college'), program = document.getElementById('pf-program');
        function fill(keep) {
            if (!program) return;
            program.innerHTML = '';
            (PROGRAMS[college.value] || []).forEach(function (p) {
                var o = document.createElement('option'); o.value = p; o.textContent = p;
                if (p === keep) o.selected = true;
                program.appendChild(o);
            });
        }
        if (college) college.addEventListener('change', function () { fill(''); });
        if (college) fill({!! json_encode(old('program', $me->program)) !!});

        /* Request a change: one dialog for SR-code and email. */
        var HINTS = {
            student_no: [{!! json_encode($emp ? 'Your new employee ID (letters, numbers, dash or slash).' : 'Example: 23-74163. It must be on the masterlist under your college.') !!}, ''],
            email: ['Must be your university address ({!! e(app(\App\Services\AccountService::class)->domainsText()) !!}).', 'You will have to confirm the new email before you can continue, and you will sign in with it.']
        };
        function setReq(field, label, current) {
            document.getElementById('crField').value = field;
            document.getElementById('crLabel').textContent = label;
            document.getElementById('crLabel2').textContent = label;
            document.getElementById('crCurrent').textContent = current;
            document.getElementById('crHint').textContent = (HINTS[field] || ['', ''])[0];
            document.getElementById('crNote').textContent = (HINTS[field] || ['', ''])[1];
        }
        document.querySelectorAll('.js-req').forEach(function (b) {
            b.addEventListener('click', function () {
                setReq(b.dataset.field, b.dataset.label, b.dataset.current);
                document.getElementById('crNew').value = ''; document.getElementById('crReason').value = '';
            });
        });
        @if (session('open_request'))
            (function () {
                var f = {!! json_encode(session('open_request')) !!}, b = document.querySelector('.js-req[data-field="' + f + '"]');
                if (b) { setReq(f, b.dataset.label, b.dataset.current); bootstrap.Modal.getOrCreateInstance(document.getElementById('changeReqModal')).show(); }
            })();
        @endif

        @if (session('open_edit'))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editProfileModal')).show();
        @endif
    })();
</script>
@endpush
