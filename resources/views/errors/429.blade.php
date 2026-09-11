@extends('errors::layout', [
    'code' => 429,
    'tone' => 'throttle',
    'title' => __('errors.429.title'),
    'message' => __('errors.429.message'),
    'showRetry' => true,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <path class="stroke draw" d="M26 62a26 26 0 0 1 48 0"/>
        <g class="shake">
            <path class="stroke" d="M50 62L64 44" stroke-width="4"/>
        </g>
        <circle class="core" cx="50" cy="62" r="4"/>
    </svg>
@endsection
