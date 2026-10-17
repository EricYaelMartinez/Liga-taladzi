<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('branding.colors.navy') }}">
    <meta name="application-name" content="{{ config('branding.name') }}">
    <meta name="description" content="{{ config('branding.tagline') }}">
    <link rel="icon" href="{{ config('branding.favicon_path') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ config('branding.logo_path') }}">
    <link rel="manifest" href="/site.webmanifest">
    <title inertia>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

