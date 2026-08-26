<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Laravel Echo reads this to sign POST /broadcasting/auth. Without it
             every private channel subscription is rejected with a 419. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'HANDYMAN') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="/icons/logo/handyman-icon.png" media="(prefers-color-scheme: light)">
        <link rel="icon" type="image/png" href="/icons/logo/handyman-icon.png" media="(prefers-color-scheme: dark)">
        <link rel="apple-touch-icon" href="/icons/logo/handyman-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|roboto-slab:700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
