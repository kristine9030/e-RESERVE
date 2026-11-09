<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\DocumentService;
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
        $trend = $this->stats->issuanceTrend();
        $stats = $this->stats->dashboard();

        return view('admin.dashboard', [
            'stats'    => $stats,
            'waiting'  => (int) $stats['docs_pending'],
            'regs'     => $this->stats->registrations(20),
            'trend'    => $trend,
            'programs' => $this->stats->programBreakdown(),
            'queue'    => $this->documents->pending(6),
            'maxTrend' => ($trend ? max(array_column($trend, 'count')) : 0) ?: 1,
        ]);
    }

    public function registrations(Request $request)
    {
        $filters = $request->only(['q', 'college', 'docs', 'ticket']);

        return view('admin.registrations', [
            'rows'    => $this->stats->registrationsPage($filters),
            'filters' => $filters,
            'stats' => $this->stats->dashboard(),
        ]);
    }

    /**
     * Disable or re-enable a student account. Disabling signs them out on their
     * next request and puts a valid ticket on hold; enabling restores it.
     */
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
        $filters = $request->only(['q', 'college']);

        return view('admin.documents', [
            'queue'    => $this->stats->pendingStudentsPage($filters),
            'filters'  => $filters,
            'colleges' => $this->stats->collegeBreakdown(),
            'reviewed' => $this->stats->reviewedDocuments(),
            'stats'    => $this->stats->dashboard(),
        ]);
    }

    /** One student, all three of their documents on one page. */
    public function student(Request $request, User $user)
    {
        $data = $this->stats->studentForReview((int) $user->id);
        abort_if($data === null, 404);

        return view('admin.student-review', $data + [
            'next'    => $this->stats->nextWaitingStudent((int) $user->id, $request->query('college')),
            'college' => (string) $request->query('college', ''),
        ]);
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
