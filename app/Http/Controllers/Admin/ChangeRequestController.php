<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Services\ChangeRequestService;
use Illuminate\Http\Request;

/** SSC decides students' requests to change their SR-code or GSuite email. */
class ChangeRequestController extends Controller
{
    public function index()
    {
        return view('admin.change-requests', [
            'pending' => ChangeRequest::with('user')->where('status', 'pending')->orderBy('created_at')->get(),
            'decided' => ChangeRequest::with('user')->where('status', '<>', 'pending')->orderByDesc('decided_at')->limit(40)->get(),
        ]);
    }

    public function decide(Request $request, ChangeRequestService $requests, int $changeRequest)
    {
        $approve = $request->input('action') === 'approve';
        $r       = $requests->decide($request->user(), $changeRequest, $approve, (string) $request->input('note', ''));

        return back()->with('flash', $r['ok'] ? flash('success', $approve ? 'Approved' : 'Returned', e($r['message'])) : flash('error', 'Not decided', e($r['message'])));
    }
}
