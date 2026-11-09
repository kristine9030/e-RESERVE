@extends('layouts.public')
@section('title', 'Terms of Use')

@section('content')
<section class="section" style="padding-top:7rem;">
    <div class="container" style="max-width:820px;">
        <span class="eyebrow"><i class="fa-solid fa-file-contract"></i> Terms</span>
        <h1 class="section-title">Terms of Use</h1>
        <p class="text-soft">By creating an account you agree to the following for {{ ev('name') }}.</p>

        <ol class="text-soft">
            <li>Only students currently enrolled at {{ ev('host') }} may register. Your SR-code is verified against the registrar's masterlist.</li>
            <li>One student, one ticket. A second account, a shared ticket or someone else's documents voids the ticket and may be referred to Student Affairs.</li>
            <li>Your ticket is personal and non-transferable. It admits one person and can be scanned once.</li>
            <li>Documents must be genuine, readable and your own. Staff may return a document; the ticket stays on hold until you upload a corrected copy.</li>
            <li>Follow the instructions of the SSC, security and university officials, and the university's code of conduct. Entry may be refused or revoked for violations.</li>
            <li>Tickets are limited and issued first-come, first-served. Registering does not guarantee a seat if capacity is reached.</li>
            <li>The SSC may disable an account that breaks these terms. How your data is handled is described in the <a href="{{ route('privacy') }}">Privacy Notice</a>.</li>
        </ol>

        <p class="cell-sub mt-5">Questions: <a href="mailto:{{ ssc('email') }}">{{ ssc('email') }}</a></p>
    </div>
</section>
@endsection
