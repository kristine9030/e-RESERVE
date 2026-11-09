@extends('layouts.base')

@section('body')
    @include('partials.public_nav')
    @yield('content')
    @include('partials.public_footer')
@endsection
