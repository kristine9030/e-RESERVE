<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Services\Audit;
use App\Services\StudentAccess;
use App\Services\TicketQuota;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** SSC decides what the public landing page reveals. */
class SiteSettingsController extends Controller
{
    public function index()
    {
        $switches = [];
        foreach (Setting::SWITCHES as $key => [$label, $help]) {
            $switches[$key] = ['label' => $label, 'help' => $help, 'on' => Setting::enabled($key)];
        }

        return view('admin.settings', [
            'switches'     => $switches,
            'sponsorCount' => Sponsor::where('active', true)->count(),
            'groups'       => TicketQuota::GROUPS,
            'limits'       => TicketQuota::limits(),
            'taken'        => TicketQuota::taken(),
            'capacity'     => TicketQuota::capacity(),
            'base'         => TicketQuota::baseCapacity(),

            'accessMode'         => StudentAccess::mode(),
            'accessOpen'         => StudentAccess::open(),
            'accessOpensAt'     => StudentAccess::opensAtText(),
            'accessOpensAtValue' => StudentAccess::opensAt()->format('Y-m-d\TH:i'),
        ]);
    }

    /** The soft-launch gate: when students may sign in and create accounts. */
    public function access(Request $request)
    {
        $mode = (string) $request->input('mode');
        abort_unless(in_array($mode, ['auto', 'open', 'closed'], true), 422);

        $ts = strtotime((string) $request->input('opens_at'));
        if ($ts === false) {
            return back()->withInput()->with('flash', flash('error', 'Opening date not saved', 'Pick a valid date and time.'));
        }
        $opensAt = date('Y-m-d H:i:s', $ts);

        Setting::store('student_access', $mode);
        Setting::store('student_access_opens_at', $opensAt);
        Audit::log('setting.student_access', 'setting', 'student_access', ['mode' => $mode, 'opens_at' => $opensAt]);

        $text = match ($mode) {
            'open'   => 'Anyone may sign in and create an account right now.',
            'closed' => 'Student sign-in and registration stay closed until you change this.',
            default  => 'Students get in automatically on ' . date('M j, Y \a\t g:i A', $ts) . '.',
        };

        return back()->with('flash', flash('success', 'Student access saved', $text));
    }

    public function update(Request $request)
    {
        $key = (string) $request->input('key');
        abort_unless(isset(Setting::SWITCHES[$key]), 422);

        $on = $request->boolean('on');
        Setting::put($key, $on);
        Audit::log('setting.' . $key, 'setting', $key, ['on' => $on]);

        $label = Setting::SWITCHES[$key][0];

        return back()->with('flash', flash('success', $on ? 'Now public' : 'Now hidden', e($label) . ($on ? ' is visible on the landing page.' : ' is hidden from the landing page.')));
    }

    /** Set how many tickets each group may have. */
    public function quotas(Request $request, TicketService $tickets)
    {
        $limits = [];
        foreach (array_keys(TicketQuota::GROUPS) as $g) {
            $limits[$g] = (int) $request->input('quota.' . $g, -1);
        }
        if ($error = TicketQuota::validate($limits)) {
            return back()->withInput()->with('flash', flash('error', 'Tickets not changed', e($error)));
        }

        $total = array_sum($limits);
        $base  = TicketQuota::baseCapacity();

        // Past the venue count needs a deliberate second step: type the new total and your password.
        if ($total > $base && $total > TicketQuota::capacity()) {
            if (!$request->has('confirm_total')) {
                return back()->withInput()->with('exceed', ['limits' => $limits, 'total' => $total, 'base' => $base]);
            }
            $okTotal = trim((string) $request->input('confirm_total')) === (string) $total;
            $okPass  = Hash::check((string) $request->input('confirm_password'), (string) $request->user()->password);
            if (!$okTotal || !$okPass) {
                Audit::log('quota.exceed.refused', 'setting', 'quota', ['total' => $total]);

                return back()->with('exceed', ['limits' => $limits, 'total' => $total, 'base' => $base])
                    ->with('flash', flash('error', 'Not confirmed', !$okTotal ? 'The number you typed is not the new total.' : 'Wrong password.'));
            }
            Audit::log('quota.exceed', 'setting', 'quota', ['total' => $total, 'venue' => $base]);
        }

        TicketQuota::save($limits);
        Audit::log('quota.set', 'setting', 'quota', $limits);
        $issued = $tickets->promoteWaiting();   // raised allotments release people already waiting

        return back()->with('flash', flash('success', 'Tickets updated', 'The split is saved.' . ($issued ? ' ' . $issued . ' waiting ' . ($issued === 1 ? 'person' : 'people') . ' got a ticket.' : '')));
    }

    /** Hand unused held seats from one group to another. */
    public function transfer(Request $request, TicketService $tickets)
    {
        $from  = (string) $request->input('from');
        $to    = (string) $request->input('to');
        $count = (int) $request->input('count');
        abort_unless(isset(TicketQuota::GROUPS[$from], TicketQuota::GROUPS[$to]) && $from !== $to, 422);

        $limits = TicketQuota::limits();
        $held   = $limits[$from] - TicketQuota::takenFor($from);
        if ($count < 1 || $count > $held) {
            return back()->with('flash', flash('error', 'Not moved', e(TicketQuota::GROUPS[$from]) . ' have only ' . max(0, $held) . ' unused seats to give.'));
        }

        $limits[$from] -= $count;
        $limits[$to]   += $count;
        TicketQuota::save($limits);
        Audit::log('quota.transfer', 'setting', 'quota', ['from' => $from, 'to' => $to, 'count' => $count]);
        $issued = $tickets->promoteWaiting();

        return back()->with('flash', flash('success', 'Seats moved', $count . ' unused seats now belong to ' . e(TicketQuota::GROUPS[$to]) . '.' . ($issued ? ' ' . $issued . ' waiting got a ticket.' : '')));
    }
}
