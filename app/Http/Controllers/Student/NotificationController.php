<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;

/** The bell in the student navbar: mark notices read, or open one. */
class NotificationController extends Controller
{
    public function readAll(Request $request)
    {
        Notice::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /** The full list: every notice, newest first, each one opens in a pop-up with its details. */
    public function index(Request $request)
    {
        $only = $request->query('show') === 'unread';
        $q    = Notice::where('user_id', $request->user()->id)->orderByDesc('id');
        if ($only) {
            $q->whereNull('read_at');
        }

        return view('student.notifications', [
            'notices' => $q->paginate(15)->withQueryString(),
            'only'    => $only,
            'open'    => (int) $request->query('open', 0),
            'unread'  => Notice::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    /** Opening the pop-up counts as reading it. */
    public function seen(Request $request, int $notice)
    {
        Notice::where('user_id', $request->user()->id)->whereKey($notice)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function open(Request $request, int $notice)
    {
        $row = Notice::where('user_id', $request->user()->id)->find($notice);   // own notices only

        if (!$row) {
            return redirect()->route('tracking');
        }

        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return redirect($row->url ?: route('tracking'));
    }
}
