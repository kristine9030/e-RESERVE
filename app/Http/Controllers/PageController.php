<?php

namespace App\Http\Controllers;

/** Privacy notice and terms of use. */
class PageController extends Controller
{
    public function privacy()
    {
        return view('pages.privacy');
    }

    public function terms()
    {
        return view('pages.terms');
    }
}
