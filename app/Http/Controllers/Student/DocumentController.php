<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use Illuminate\Http\Request;

/** Step 2: upload the three required PDFs. Uploading the third issues the ticket. */
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents)
    {
    }

    public function index()
    {
        if (auth()->user()->isEmployee()) {      // faculty / staff upload nothing
            return redirect()->route('dashboard');
        }

        $progress = document_progress();

        return view('student.documents', [
            'user'     => current_user(),
            'docs'     => user_documents(),
            'progress' => $progress,
            'ticket'   => get_ticket(),
            'pct'      => (int) round($progress['uploaded'] / $progress['total'] * 100),
            'maxBytes' => (int) config('ereserve.doc_max_bytes'),
        ]);
    }

    public function store(Request $request)
    {
        $type   = (string) $request->input('doc_type', '');
        $result = $this->documents->submit($request->user(), $type, $request->file('document'));

        if (!$result['ok']) {
            return redirect(route('documents.index') . '#doc-' . ($this->documents->isType($type) ? $type : 'waiver'))
                ->with('flash', flash('error', 'Could Not Upload', e($result['message'])));
        }

        if (in_array($result['ticket'], ['issued', 'restored'], true)) {
            return redirect()->route('ticket')->with('flash', flash(
                'success', $result['ticket'] === 'issued' ? 'Your ticket is ready!' : 'Ticket restored', e($result['message'])
            ));
        }

        // All three are in: show the "now wait for SSC" pop-up instead of the plain toast.
        if (!empty($result['all_in'])) {
            return redirect()->route('documents.index')->with('submitted_popup', true)->with('doc_warnings', $result['warnings'] ?? []);
        }

        if (!empty($result['warnings'])) {
            return redirect()->route('documents.index')->with('flash', flash('warning', 'Uploaded, but please check',
                e($result['message']) . '<br><br>' . implode('<br>', array_map('e', $result['warnings']))
                . '<br><br>Make sure this is your own file. If it is, SSC will still review it; if not, upload the right one.'));
        }

        return redirect()->route('documents.index')->with('flash', flash('success', 'Uploaded', e($result['message'])));
    }
}
