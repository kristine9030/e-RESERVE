<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Switch a dual account (student/faculty/sponsor + SSC) between its two sides. */
class ModeController extends Controller
{
    public function switch(Request $request, string $mode)
    {
        $user = $request->user();
        abort_unless(in_array($mode, ['ssc', 'student'], true) && $user->isDualAccount(), 404);

        session(['ssc_mode' => $mode === 'ssc']);

        return redirect($mode === 'ssc' ? route('admin.dashboard') : route('dashboard'));
    }
}
