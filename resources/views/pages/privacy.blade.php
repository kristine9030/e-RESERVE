@extends('layouts.public')
@section('title', 'Privacy Notice')

@section('content')
<section class="section" style="padding-top:7rem;">
    <div class="container" style="max-width:820px;">
        <span class="eyebrow"><i class="fa-solid fa-shield-halved"></i> Data privacy</span>
        <h1 class="section-title">Privacy Notice</h1>
        <p class="text-soft">How the {{ ev('org') }} handles your information in e-RESERVE, under the Data Privacy Act of 2012 (RA 10173).</p>

        <h5 class="mt-4">What we collect</h5>
        <ul class="text-soft">
            <li>Your name, SR-code, college, programme, year level, GSuite address and (optionally) mobile number.</li>
            <li>The documents you upload: for students the signed waiver, Certificate of Registration and school ID; for faculty and staff your employee ID; for sponsors a proof of sponsorship.</li>
            <li>Sign-in and activity records (what was reviewed, issued or scanned, when, and by whom).</li>
        </ul>

        <h5 class="mt-4">Why</h5>
        <p class="text-soft">
            Only to confirm that you are an enrolled student (your SR-code is checked against the registrar's masterlist),
            to issue one ticket for {{ ev('name') }}, and to record attendance at the gate. We do not sell or share your data
            and use it for nothing else.
        </p>

        <h5 class="mt-4">Who can see it</h5>
        <p class="text-soft">
            You, and authorised SSC staff for verification. Documents are stored outside the public website and are
            served only after the system checks who is asking. Every time staff open or review a document, it is logged.
        </p>

        <h5 class="mt-4">How long we keep it</h5>
        <p class="text-soft">
            For the event and its reporting and audit needs, then it is deleted or archived according to university records policy.
        </p>

        <h5 class="mt-4">Your rights</h5>
        <p class="text-soft">
            You may ask to see, correct or delete your data, or object to its processing. Email
            <a href="mailto:{{ ssc('email') }}">{{ ssc('email') }}</a> and we will respond within a reasonable time.
        </p>

        <p class="cell-sub mt-5">{{ ev('org') }} · {{ ev('host') }}</p>
    </div>
</section>
@endsection
