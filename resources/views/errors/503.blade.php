@extends('errors::layout', [
    'code' => 503,
    'tone' => 'maintenance',
    'title' => __('errors.503.title'),
    'message' => __('errors.503.message'),
    'showRetry' => true,
    'showBack' => false,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="tick">
            <path class="stroke" d="M50 30v6M50 64v6M30 50h6M64 50h6M35.9 35.9l4.2 4.2M59.9 59.9l4.2 4.2M35.9 64.1l4.2-4.2M59.9 40.1l4.2-4.2" stroke-width="4"/>
            <circle class="stroke" cx="50" cy="50" r="10" stroke-width="5"/>
        </g>
        <circle class="core" cx="50" cy="50" r="3"/>
    </svg>
@endsection
