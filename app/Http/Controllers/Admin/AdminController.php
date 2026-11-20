<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\DocumentService;
use App\Services\RecordsExport;
use App\Services\Stats;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Staff dashboard, student list and the document review queue. */
class AdminController extends Controller
{
    public function __construct(private Stats $stats, private DocumentService $documents)
    {
    }

    public function dashboard()
    {
        $stats = $this->stats->dashboard();

        return view('admin.dashboard', [
            'stats'    => $stats,
            'waiting'  => $this->stats->waitingPeople(),
            'regs'     => $this->stats->registrations(20),
            'audience' => $this->stats->audienceBreakdown(),
            'colleges' => collect($this->stats->collegeBreakdown())->sortByDesc('students')->values()->all(),
            'queue'    => $this->stats->pendingStudentsPage([], 6)->items(),   // people, first submitted first
            'programsBy' => $this->stats->programsByCollege(),
            'groupDetail' => $this->stats->groupDetails(),
        ]);
    }

    public function registrations(Request $request)
    {
        $filters = $request->only(['q', 'college', 'docs', 'ticket', 'program', 'type', 'sort']);
        $filters['type'] = (string) ($filters['type'] ?? 'all');   // 'all' = every record; the sidebar opens one group at a time
        if (($filters['sort'] ?? '') !== 'newest') {
            unset($filters['sort']);   // "first submitted first" is the normal order, so it is not kept in the URL
        }

        return view('admin.registrations', [
            'rows'    => $this->stats->registrationsPage($filters),
            'filters' => $filters,
            'kpi'     => $this->stats->recordsKpis((string) $filters['type']),
        ]);
    }

    /** Download the Records list (with the current filters) as Excel, PDF or CSV. */
    public function recordsExport(Request $request, string $format, RecordsExport $export)
    {
        $data = $export->build($request->only(RecordsExport::FILTERS));
        Audit::log('records.export', 'records', $format, ['group' => $data['title'], 'rows' => count($data['rows'])]);

        return match ($format) {
            'xlsx' => response($export->xlsx($data), 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $export->filename($data, 'xlsx') . '"',
                'Cache-Control'       => 'no-store',
            ]),
            'pdf' => response($export->pdf($data), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $export->filename($data, 'pdf') . '"',
                'Cache-Control'       => 'no-store',
            ]),
            default => response($export->csv($data), 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $export->filename($data, 'csv') . '"',
                'Cache-Control'       => 'no-store',
            ]),
        };
    }

    /** Signed link (expires in two hours): plain CSV for Google Sheets' IMPORTDATA. */
    public function recordsFeed(Request $request, RecordsExport $export)
    {
        $data = $export->build($request->only(RecordsExport::FILTERS));

        return response($export->csv($data, false), 200, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }

    /**
     * Disable or re-enable a student account. Disabling signs them out on their
     * next request and puts a valid ticket on hold; enabling restores it.
     */
    /** Delete an account for good (and, if asked, tell the person by email). */
    public function deleteAccount(Request $request, User $user, \App\Services\AccountDeleter $deleter)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $result = $deleter->delete($user, (string) $request->input('note'), $request->boolean('notify'), $request->user());

        return back()->with('flash', flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Account deleted' : 'Not deleted', e($result['message'])));
    }

    /** The SSC says this address is fine after all: let e-RESERVE email it again. */
    public function clearEmailFlag(User $user)
    {
        abort_if($user->isStaff(), 403);

        if ($user->email_bounced_at !== null) {
            $user->forceFill(['email_bounced_at' => null, 'email_bounce_type' => null])->save();
            Audit::log('email.flag_cleared', 'user', (string) $user->id, ['student' => $user->name]);
        }

        return back()->with('flash', flash(
            'success', 'Email cleared',
            e($user->name) . '’s address will receive email again. If Amazon still blocks it, it also has to be removed from the SES suppression list.'
        ));
    }

    public function setStatus(Request $request, User $user, TicketService $tickets)
    {
        abort_if($user->isStaff(), 403, 'Staff accounts are managed with artisan.');

        $disable = $request->input('action') === 'disable';
        $user->forceFill(['status' => $disable ? 'disabled' : 'active'])->save();

        if ($disable) {
            DB::table('tickets')->where('user_id', $user->id)->where('status', 'valid')
                ->update(['status' => 'void', 'voided_at' => now()]);
        } else {
            $tickets->sync($user->id);
        }
        $tickets->forget();

        Audit::log($disable ? 'user.disable' : 'user.enable', 'user', (string) $user->id, ['student' => $user->name]);

        return back()->with('flash', flash(
            'success', $disable ? 'Account disabled' : 'Account enabled',
            e($user->name) . ($disable ? ' can no longer sign in and their ticket is on hold.' : ' can sign in again.')
        ));
    }

    public function documents(Request $request)
    {
        $filters = $request->only(['q', 'college', 'type', 'status', 'sort']);
        if (!in_array($filters['type'] ?? '', ['student', 'employee', 'sponsor'], true)) {
            unset($filters['type']);
        }
        // "Waiting for review" and "first submitted first" are the defaults, so they are not kept in the URL.
        if (!array_key_exists($filters['status'] ?? '', \App\Services\Stats::REVIEW_STATUSES) || $filters['status'] === 'pending') {
            unset($filters['status']);
        }
        if (($filters['sort'] ?? '') !== 'newest') {
            unset($filters['sort']);
        }

        $queue = $this->stats->pendingStudentsPage($filters);

        // People leave the list as they are approved, so the page you were on may no longer exist: go to the last one.
        if ($queue->lastPage() > 0 && $queue->currentPage() > $queue->lastPage()) {
            return redirect()->route('admin.documents', $filters + ['page' => $queue->lastPage()]);
        }

        // "Back to the queue" on a student's page returns to this exact page, filters and all.
        session(['review_list' => $request->getRequestUri()]);

        return view('admin.documents', [
            'queue'    => $queue,
            'counts'   => $this->stats->pendingGroupCounts($filters),
            'filters'  => $filters,
        ]);
    }

    /** One student, all three of their documents on one page. */
    public function student(Request $request, User $user)
    {
        $data = $this->stats->studentForReview((int) $user->id);
        abort_if($data === null, 404);

        // Remember the students opened in this review session so "Previous" can step back to
        // them, even after they were approved and left the waiting queue.
        $trail = array_values(array_map('intval', (array) session('review_trail', [])));
        $pos   = array_search((int) $user->id, $trail, true);
        if ($pos === false) {
            $trail[] = (int) $user->id;
            $pos     = count($trail) - 1;
        }
        $previous = $pos > 0 ? (int) $trail[$pos - 1] : null;
        session(['review_trail' => array_slice($trail, -100)]);

        // Remember this student's place in the queue while their files are still waiting, so that after
        // you approve or return them "Next" carries on from the same spot (their files no longer count as waiting).
        $marks = (array) session('review_marks', []);
        $mark  = $this->stats->queuePosition((int) $user->id);
        if ($mark !== null) {
            $marks[(int) $user->id] = $mark;
            session(['review_marks' => array_slice($marks, -200, null, true)]);
        } else {
            $mark = $marks[(int) $user->id] ?? null;
        }

        return view('admin.student-review', $data + [
            'previous' => $previous,
            'next'    => $this->stats->nextWaitingStudent((int) $user->id, $request->query('college'), $mark),
            'college' => (string) $request->query('college', ''),
            'backUrl' => $this->backToList($request->query('college')),
        ]);
    }

    /** The list page the reviewer came from (same page number and filters), or the plain queue. */
    private function backToList(?string $college): string
    {
        $saved = (string) session('review_list', '');

        // only ever a path inside the admin documents list
        return str_starts_with($saved, '/admin/documents') && !str_starts_with($saved, '/admin/documents/student')
            ? $saved
            : route('admin.documents', array_filter(['college' => $college]));
    }

    /**
     * Approve several documents in one go. Each one still goes through the same
     * locked review, so a file replaced since the page loaded is skipped, not approved.
     */
    public function bulkReview(Request $request)
    {
        $ids      = array_slice(array_unique(array_map('intval', (array) $request->input('ids', []))), 0, 50);
        $versions = (array) $request->input('versions', []);

        if ($ids === []) {
            return back()->with('flash', flash('error', 'Nothing selected', 'Tick the documents you have checked first.'));
        }

        $ok = 0;
        $skipped = 0;
        $flagged = 0;
        foreach ($ids as $id) {
            // A file the automatic checks warned about is reviewed one by one, never in bulk.
            $checks = \App\Models\Document::find($id)?->auto_checks;      // already decoded by the model cast
            if (\App\Services\DocumentChecker::needsLook($checks)) {
                $flagged++;
                continue;
            }
            $r = $this->documents->review($request->user(), $id, 'approved', '', isset($versions[$id]) ? (string) $versions[$id] : null);
            $r['ok'] ? $ok++ : $skipped++;
        }

        return back()->with('flash', flash(
            $ok > 0 ? 'success' : 'error', 'Bulk approval',
            $ok . ' approved'
            . ($skipped ? ', ' . $skipped . ' skipped (already decided or replaced by the student)' : '')
            . ($flagged ? ', ' . $flagged . ' left for you to check one by one (the automatic checks flagged them)' : '') . '.'
        ));
    }

    public function review(Request $request)
    {
        $id      = (int) $request->input('document_id');
        $action  = (string) $request->input('action');
        $version = $request->filled('version') ? (string) $request->input('version') : null;

        if ($action === 'approve') {
            $r = $this->documents->review($request->user(), $id, 'approved', '', $version);
            $flash = $r['ok'] ? flash('success', 'Document Approved', e($r['message'])) : flash('error', 'Could Not Approve', e($r['message']));
        } elseif ($action === 'reject') {
            $r = $this->documents->review($request->user(), $id, 'rejected', (string) $request->input('reason', ''), $version);
            $flash = $r['ok'] ? flash('success', 'Document Returned', e($r['message'])) : flash('error', 'Could Not Return', e($r['message']));
        } else {
            $flash = flash('error', 'Unknown action', 'Nothing was changed.');
        }

        return back()->with('flash', $flash);
    }
}
