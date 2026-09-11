@php
    $currentLocale = app()->getLocale();
@endphp

<div
    aria-label="{{ __('app.language') }}"
    role="group"
    style="display: flex; align-items: center; justify-content: center; gap: 0.375rem; margin-bottom: {{ ($onLoginPage ?? false) ? '1.25rem' : '0' }};"
>
    @foreach (['tr' => 'TR', 'en' => 'EN'] as $locale => $label)
        <x-filament::button
            :aria-current="$currentLocale === $locale ? 'true' : 'false'"
            :color="$currentLocale === $locale ? 'primary' : 'gray'"
            :href="request()->fullUrlWithQuery(['locale' => $locale])"
            size="xs"
            tag="a"
        >
            {{ $label }}
        </x-filament::button>
    @endforeach
</div>
