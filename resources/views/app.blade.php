@php
    $initialTheme = in_array(auth()->user()?->profile?->theme, ['blue', 'green', 'dark'], true)
        ? auth()->user()->profile->theme
        : 'blue';
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="{{ $initialTheme }}" class="{{ $initialTheme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0F0F0F">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'CatatCuan') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
