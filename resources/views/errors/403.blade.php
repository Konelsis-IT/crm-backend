@php
    $frameworkMessages = ['', 'Forbidden', 'This action is unauthorized.', 'Unauthorized.'];
    $detail = isset($exception) && ! in_array($exception->getMessage(), $frameworkMessages, true)
        ? $exception->getMessage()
        : null;
@endphp

@extends('errors::layout', [
    'code' => 403,
    'tone' => 'forbidden',
    'title' => __('errors.403.title'),
    'message' => __('errors.403.message'),
    'detail' => $detail,
])

@section('glyph')
    <svg class="glyph" viewBox="0 0 100 100" aria-hidden="true">
        <circle class="ring" cx="50" cy="50" r="42"/>
        <circle class="arc" cx="50" cy="50" r="42"/>
        <g class="shake">
            <path class="stroke draw" d="M36 46v-8a14 14 0 0 1 28 0v8"/>
            <rect x="30" y="46" width="40" height="28" rx="6" fill="#dc2626"/>
            <circle cx="50" cy="58" r="4" fill="#fff"/>
            <path d="M50 60v7" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
        </g>
    </svg>
@endsection
