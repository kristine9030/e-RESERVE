<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use App\Support\DocumentSets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * Student documents: validated by content, renamed, stored outside the web
 * root and served only to their owner or to staff (see DocumentFileController).
 */
class DocumentService
{
    public function __construct(private TicketService $tickets, private DocumentChecker $checker)
    {
    }

    public function isType(?string $key): bool
    {
        return $key !== null && array_key_exists($key, config('ereserve.documents'));
    }

    /**
     * Validate and store an uploaded PDF.
     *
     * @return array{ok:bool,message:string,stored:?string,original:?string,size:int}
     */
    public function store(?UploadedFile $file, string $label): array
    {
        $fail = static fn(string $m) => ['ok' => false, 'message' => $m, 'stored' => null, 'original' => null, 'size' => 0];
        $max  = (int) config('ereserve.doc_max_bytes');
        $mb   = (int) ($max / 1024 / 1024);

        if ($file === null) {
            return $fail('Choose the PDF for your ' . $label . ' first.');
        }
        if (!$file->isValid()) {
            return in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? $fail('The ' . $label . ' is too large. Keep each PDF under ' . $mb . ' MB.')
                : $fail('The upload did not complete. Please try again.');
        }
        if ($file->getSize() > $max) {
            return $fail('The ' . $label . ' is too large. Keep each PDF under ' . $mb . ' MB.');
        }
        if ($file->getSize() < 200) {
            return $fail('That file looks empty. Re-export the ' . $label . ' as a PDF and try again.');
        }

        // Trust the bytes, not the name or the type the browser sent.
        $path = $file->getRealPath();
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $head = (string) file_get_contents($path, false, null, 0, 1024);

        if ($mime !== 'application/pdf' || !str_contains($head, '%PDF-')) {
            return $fail('Only PDF files are accepted. Photos and Word files must be saved as PDF first.');
        }

        // Password-protected or cut-off PDFs cannot be reviewed: say so now, not after SSC finds out.
        $look = $this->checker->inspect($path, $label);
        if (!$look['ok']) {
            return $fail($look['message']);
        }

        $name = date('Ymd') . '-' . bin2hex(random_bytes(16)) . '.pdf';
        Storage::disk('local')->putFileAs('documents', $file, $name);

        $original = preg_replace('/[^\w\s.\-()]+/u', '', basename((string) $file->getClientOriginalName())) ?: 'document.pdf';

        return [
            'ok'       => true,
            'message'  => 'File received.',
            'stored'   => $name,
            'original' => mb_substr($original, 0, 180),
            'size'     => (int) $file->getSize(),
        ];
    }

    /** Absolute path of a stored document, or null (rejects path trickery). */
    public function path(string $storedName): ?string
    {
        if (!preg_match('/^\d{8}-[0-9a-f]{32}\.pdf$/', $storedName)) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists('documents/' . $storedName) ? $disk->path('documents/' . $storedName) : null;
    }

    /**
     * Save one of the holder's required documents (three for a student, one for faculty/staff or a sponsor).
     *
     * @return array{ok:bool,message:string,ticket:string}
     */
    public function submit(User $user, string $type, ?UploadedFile $file): array
    {
        $fail = static fn(string $m) => ['ok' => false, 'message' => $m, 'ticket' => 'unchanged'];

        if ($user->isStaff()) {
            return $fail('Sign in with a student account first.');
        }
        if (!$this->isType($type) || !in_array($type, DocumentSets::keysFor($user->member_type), true)) {
            return $fail('That is not one of the required documents.');
        }

        $label = config("ereserve.documents.$type.label");

        $key = 'upload:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 12)) {
            return $fail('Too many uploads in a short time. Try again in ' . RateLimiter::availableIn($key) . ' seconds.');
        }
        RateLimiter::hit($key, 600);

        $current = $this->tickets->documents($user->id)[$type] ?? null;

        if ($current && $current['status'] === 'approved') {
            return $fail('Your ' . $label . ' has already been approved, so it is locked.');
        }
        if (($this->tickets->ticketFor($user->id)['status'] ?? '') === 'used') {
            return $fail('Your ticket has already been used at the gate.');
        }

        $stored = $this->store($file, $label);
        if (!$stored['ok']) {
            return $fail($stored['message']);
        }

        // Automatic checks on the stored file (what staff see next to it).
        $verdict = ['hash' => '', 'checks' => []];
        try {
            $verdict = $this->checker->run($user, $type, Storage::disk('local')->path('documents/' . $stored['stored']));
        } catch (\Throwable $e) {
            report($e);   // a checker problem must never block an upload
        }

        // The row is locked while we decide, so an approval that lands at the
        // same moment cannot be overwritten by this upload.
        $previous = null;
        try {
            $saved = DB::transaction(function () use ($user, $type, $stored, $verdict, &$previous) {
                $row = Document::where('user_id', $user->id)->where('doc_type', $type)->lockForUpdate()->first();

                if ($row && $row->status === 'approved') {
                    return false;
                }

                $previous = $row?->stored_name;

                Document::updateOrCreate(
                    ['user_id' => $user->id, 'doc_type' => $type],
                    [
                        'original_name' => $stored['original'],
                        'stored_name'   => $stored['stored'],
                        'size_bytes'    => $stored['size'],
                        'file_hash'     => $verdict['hash'] ?: null,
                        'auto_checks'   => $verdict['checks'],
                        'status'        => 'submitted',
                        'reviewed_by'   => null,
                        'reviewed_at'   => null,
                        'review_note'   => null,
                        'uploaded_ip'   => substr((string) request()->ip(), 0, 45),
                        'uploaded_at'   => now(),
                    ]
                );

                return true;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete('documents/' . $stored['stored']);   // no orphan file
            throw $e;
        }

        if (!$saved) {
            Storage::disk('local')->delete('documents/' . $stored['stored']);

            return $fail('Your ' . $label . ' has already been approved, so it is locked.');
        }

        // The replaced file is of no use to anyone now (deleted only after the commit).
        if ($previous && $this->path((string) $previous)) {
            Storage::disk('local')->delete('documents/' . $previous);
        }

        $this->tickets->forget();
        Audit::log('document.upload', 'document', $type, ['user_id' => $user->id, 'file' => $stored['original']]);

        $sync = $this->tickets->sync($user->id);

        $progress = $this->tickets->progress($user->id);
        $message  = match (true) {
            $sync['state'] === 'issued'   => 'Your ticket number is ' . $sync['ticket_no'] . '.',
            $sync['state'] === 'restored' => 'Thanks — your ticket ' . $sync['ticket_no'] . ' is valid again.',
            $progress['missing'] === []   => $label . ' uploaded.' . ($progress['total'] > 1 ? ' All ' . $progress['total'] . ' are in.' : '') . ' SSC will review ' . ($progress['total'] > 1 ? 'them' : 'it') . ', and your ticket is issued once approved.',
            default                       => $label . ' uploaded.',
        };

        // Everything is in and nothing is waiting on the student: tell them to sit tight.
        $allIn = $progress['missing'] === [] && !$progress['blocked'] && !$progress['all_approved'];
        if ($allIn) {
            Notifier::submitted((int) $user->id);
        }

        // Only the warnings meant for the student (e.g. "your SR-code is not in this COR").
        $warnings = array_values(array_map(
            fn ($c) => $c['t'],
            array_filter($verdict['checks'], fn ($c) => ($c['s'] ?? '') === 'warn' && !empty($c['student']))
        ));

        return ['ok' => true, 'message' => $message, 'ticket' => $sync['state'], 'all_in' => $allIn, 'warnings' => $warnings];
    }

    /**
     * Staff decision on one document.
     *
     * @return array{ok:bool,message:string}
     */
    public function review(User $admin, int $documentId, string $decision, string $note = '', ?string $version = null): array
    {
        if (!$admin->isAdmin()) {
            return ['ok' => false, 'message' => 'Only SSC staff can review documents.'];
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'message' => 'Unknown decision.'];
        }

        $note = trim($note);
        if ($decision === 'rejected' && mb_strlen($note) < 5) {
            return ['ok' => false, 'message' => 'Tell the student what to fix (at least a few words).'];
        }

        $doc = null;
        $err = DB::transaction(function () use ($documentId, $decision, $note, $admin, $version, &$doc) {
            $doc = Document::with('user')->lockForUpdate()->find($documentId);

            if (!$doc) {
                return 'That document no longer exists.';
            }
            // Only a file still waiting for a decision can be decided: stops two
            // reviewers racing, and stops a re-review of an already-final file.
            if ($doc->status !== 'submitted') {
                return 'That document was already ' . ($doc->status === 'approved' ? 'approved' : 'returned') . '. Reload the queue.';
            }
            // The student replaced the file after this page was loaded: the
            // reviewer has not seen what they are about to approve.
            if ($version !== null && $version !== (string) $doc->uploaded_at?->timestamp) {
                return 'The student just uploaded a new file. Reload the queue and review the latest one.';
            }

            $doc->update([
                'status'      => $decision,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note === '' ? null : mb_substr($note, 0, 250),
            ]);

            return null;
        });

        if ($err !== null) {
            return ['ok' => false, 'message' => $err];
        }

        $this->tickets->forget();
        Audit::log('document.' . ($decision === 'approved' ? 'approve' : 'reject'), 'document', (string) $documentId, [
            'student' => $doc->user->name, 'type' => $doc->doc_type, 'note' => $note,
        ]);

        $sync  = $this->tickets->sync((int) $doc->user_id);

        if ($decision === 'rejected') {
            Notifier::documentReturned((int) $doc->user_id, (string) $doc->doc_type, $note);
        } elseif ($sync['state'] === 'full') {
            Notifier::waitlisted((int) $doc->user_id);
        } elseif ($sync['state'] !== 'issued') {
            Notifier::documentApproved((int) $doc->user_id, (string) $doc->doc_type);   // 'issued' sends its own ticket notice
        }
        $extra = match ($sync['state']) {
            'voided' => ' Their ticket was voided until they upload a corrected file.',
            'issued' => ' All three are approved, so their ticket ' . $sync['ticket_no'] . ' was issued.',
            'full'   => ' All three are approved, but there are no seats left for their group.',
            default  => '',
        };

        return [
            'ok'      => true,
            'message' => config("ereserve.documents.{$doc->doc_type}.short") . ' of ' . $doc->user->name
                       . ($decision === 'approved' ? ' approved.' : ' rejected.') . $extra,
        ];
    }

    /** Documents waiting for a staff decision, oldest first. */
    public function pending(?int $limit = null): array
    {
        return TicketService::rows(
            'SELECT d.*, u.name AS student_name, u.student_no, u.program, u.year_level
             FROM documents d JOIN users u ON u.id = d.user_id
             WHERE d.status = "submitted"
             ORDER BY d.uploaded_at ASC, d.id ASC' . ($limit ? ' LIMIT ' . max(1, $limit) : '')
        );
    }
}
