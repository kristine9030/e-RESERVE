<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Dashboard, ticket, tracking, profile and the official waiver download. */
class StudentController extends Controller
{
    /** The student dashboard: what to do next, document status and the ticket. */
    public function dashboard()
    {
        $user     = auth()->user();
        $verified = $user->hasVerifiedEmail();
        $progress = document_progress();
        $ticket   = get_ticket();
        $docCfg   = required_documents();

        // The one thing to do next — drives the big call-to-action card.
        if (!$verified) {
            $next = ['fa-envelope-open-text', 'Confirm your email', 'Open the link we sent to ' . $user->email . ' so we know the account is yours.', 'Resend link', route('verify')];
        } elseif ($progress['blocked']) {
            $next = ['fa-triangle-exclamation', 'Replace the returned document', 'Staff sent a document back with a note. Upload a corrected PDF to restore your ticket.', 'Fix it now', route('documents.index')];
        } elseif ($progress['missing']) {
            $names = implode(', ', array_map(static fn($k) => $docCfg[$k]['short'], $progress['missing']));
            $next = ['fa-file-arrow-up', 'Upload your documents', 'Still needed: ' . $names . '. Your ticket is issued once SSC approves ' . (count($docCfg) > 1 ? 'all of them' : 'it') . '.', 'Upload now', route('documents.index')];
        } elseif ($ticket && !$ticket['checked_in']) {
            $next = ['fa-qrcode', 'Your ticket is ready', 'Show the QR code at the gate on event night. Screenshot it or print a copy as backup.', 'Open my ticket', route('ticket')];
        } else {
            $next = ['fa-circle-check', 'You’re all set', 'You have been scanned in. Enjoy SIGLA!', 'View ticket', route('ticket')];
        }

        $hour = (int) date('G');

        return view('student.dashboard', [
            'user'      => current_user(),
            'docs'      => user_documents(),
            'progress'  => $progress,
            'ticket'    => $ticket,
            'timeline'  => tracking_timeline(),
            'next'      => $next,
            'firstName' => explode(' ', $user->name)[0],
            'greeting'  => greeting_for($hour),
            'stage'     => registration_stage_label(),
        ]);
    }

    public function ticket()
    {
        return view('student.ticket', [
            'ticket'   => get_ticket(),
            'progress' => document_progress(),
            'user'     => current_user(),
        ]);
    }

    /** The ticket as a JPG picture, to save to the phone. Only a valid ticket can be saved. */
    public function ticketImage(Request $request, \App\Services\TicketImage $images)
    {
        $ticket = get_ticket();

        if (!$ticket || $ticket['status'] === 'void') {
            return redirect()->route('ticket')->with('flash', flash('warning', 'No ticket to save',
                $ticket ? 'Your ticket is on hold until you fix the returned document.' : 'Your ticket appears once SSC approves your documents.'));
        }

        $file = 'ticket-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $ticket['ticket_no']) . '.jpg';

        return response($images->jpg($ticket), 200, [
            'Content-Type'        => 'image/jpeg',
            'Content-Disposition' => ($request->boolean('view') ? 'inline' : 'attachment') . '; filename="' . $file . '"',
            'Cache-Control'       => 'private, no-store',     // a ticket picture is personal
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function tracking()
    {
        $timeline = tracking_timeline();
        $doneCt   = count(array_filter($timeline, static fn($s) => $s['state'] === 'done'));

        return view('student.tracking', [
            'progress' => document_progress(),
            'docs'     => user_documents(),
            'ticket'   => get_ticket(),
            'timeline' => $timeline,
            'doneCt'   => $doneCt,
            'pct'      => (int) round($doneCt / count($timeline) * 100),
            'stage'    => registration_stage_label(),
        ]);
    }

    public function profile(Request $request)
    {
        return view('student.profile', [
            'user'     => current_user(),
            'progress' => document_progress(),
            'docs'     => user_documents(),
            'ticket'   => get_ticket(),
            'signedInAt' => session('logged_in_at', now()->toDateTimeString()),
        ]);
    }

    /** The official SIGLA waiver (BatStateU-FO-SOA-03), exactly as the university issued it. */
    public function waiver()
    {
        $path = resource_path('forms/SIGLA-2026-Waiver.pdf');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'SIGLA-2026-Waiver.pdf', ['Content-Type' => 'application/pdf']);
    }
}
