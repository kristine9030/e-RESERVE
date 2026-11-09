{{ $heading }}

Hi {{ $studentName }},

@foreach ($paragraphs as $p)
{{ $p }}

@endforeach
@if ($highlight)
Ticket number: {{ $highlight }}

@endif
@if ($buttonUrl)
{{ $buttonLabel }}: {{ $buttonUrl }}

@endif
{{ config('ereserve.event.org') }} · {{ config('ereserve.event.host') }}
