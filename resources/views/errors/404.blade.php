@extends('errors::layout', [
    'code' => 404,
    'tone' => 'not_found',
    'title' => __('errors.404.title'),
    'message' => __('errors.404.message'),
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="wobble">
            <circle class="stroke draw" cx="45" cy="45" r="16"/>
            <path class="stroke draw" d="M57 57l12 12"/>
            <path class="stroke" d="M40 40.5c0-3.5 2.5-5.5 5.5-5.5s5 2 5 4.5c0 3.5-5 3.5-5 8" stroke-width="3.5"/>
            <circle class="core" cx="45.5" cy="52" r="1.8"/>
        </g>
    </svg>
@endsection
