@extends('errors::layout', [
    'code' => 419,
    'tone' => 'expired',
    'title' => __('errors.419.title'),
    'message' => __('errors.419.message'),
    'showLogin' => true,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <circle class="stroke" cx="50" cy="50" r="24"/>
        <g class="tick">
            <path class="stroke" d="M50 50V32" stroke-width="4"/>
        </g>
        <path class="stroke" d="M50 50l12 8" stroke-width="4"/>
        <circle class="core" cx="50" cy="50" r="3"/>
    </svg>
@endsection
