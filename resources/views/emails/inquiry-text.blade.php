New sponsor inquiry: {{ $inquiry->company }}

Name: {{ $inquiry->contact_name }}
Email: {{ $inquiry->email }}
@if (filled($inquiry->phone))
Phone: {{ $inquiry->phone }}
@endif

@if (filled($inquiry->message))
{{ $inquiry->message }}

@endif
Reply to this email to answer them directly.
Sponsor inquiries in e-RESERVE: {{ route('admin.sponsors') }}

{{ config('ereserve.event.org') }} · {{ config('ereserve.event.host') }}
