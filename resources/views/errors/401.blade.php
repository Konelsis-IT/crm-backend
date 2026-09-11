@extends('errors::layout', [
    'code' => 401,
    'tone' => 'auth',
    'title' => __('errors.401.title'),
    'message' => __('errors.401.message'),
    'showLogin' => true,
    'showBack' => false,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="wobble">
            <circle class="stroke draw" cx="40" cy="42" r="11"/>
            <path class="stroke draw" d="M48 50l22 22M62 64l6-6M68 70l6-6"/>
            <circle class="core" cx="40" cy="42" r="3"/>
        </g>
    </svg>
@endsection
