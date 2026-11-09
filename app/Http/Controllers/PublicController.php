<?php

namespace App\Http\Controllers;

use App\Services\Stats;

/** The public landing page. */
class PublicController extends Controller
{
    public function index(Stats $stats)
    {
        $cta = auth()->check()
            ? (auth()->user()->isStaff() ? auth()->user()->homeRoute() : route('documents.index'))
            : route('register');

        return view('landing', ['stats' => $stats->dashboard(), 'cta' => $cta, 'sponsors' => \App\Models\Sponsor::forLanding()]);
    }
}
