@php
    $statusCode = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
@endphp

@extends('errors::layout', [
    'code' => $statusCode,
    'tone' => 'server',
    'title' => __('errors.5xx.title'),
    'message' => __('errors.5xx.message'),
    'showRetry' => true,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="wobble">
            <path class="stroke draw" d="M50 26L74 68H26z"/>
            <path class="stroke" d="M50 42v13" stroke-width="4.5"/>
            <circle class="core" cx="50" cy="61" r="2.6"/>
        </g>
    </svg>
@endsection
