@php
    $statusCode = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400;
@endphp

@extends('errors::layout', [
    'code' => $statusCode,
    'tone' => 'client',
    'title' => __('errors.4xx.title'),
    'message' => __('errors.4xx.message'),
    'detail' => isset($exception) && $exception->getMessage() !== '' ? $exception->getMessage() : null,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="wobble">
            <path class="stroke draw" d="M38 38l24 24M62 38L38 62"/>
        </g>
    </svg>
@endsection
