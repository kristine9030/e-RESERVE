<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Sponsor;
use App\Services\Stats;

/** The public landing page. */
class PublicController extends Controller
{
    public function index(Stats $stats)
    {
        $cta = auth()->check()
            ? (auth()->user()->isStaff() ? auth()->user()->homeRoute() : route('documents.index'))
            : route('register');

        // the counters are re-read at most every 20 seconds, not on every visit
        $landingStats = \Illuminate\Support\Facades\Cache::remember('landing.stats', 20, fn () => $stats->dashboard());

        $showSponsors = Setting::enabled('show_sponsors');

        return view('landing', [
            'stats'        => $landingStats,
            'cta'          => $cta,
            'showSlots'    => Setting::enabled('show_slots'),
            'showSponsors' => $showSponsors,
            'sponsors'     => $showSponsors ? Sponsor::forLanding() : [],
        ]);
    }
}
