<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-admin-theme="{{ \App\Enums\AdminTheme::sanitize(setting('theme_admin_theme', \App\Enums\AdminTheme::Default->value)) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-inertia>{{ config('app.name', 'Laravel') }}</title>
    @inertiaHead

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: var(--background, oklch(1 0 0));
        }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {!! app(\App\Services\ZiggyRouteFilter::class)->render(auth()->user()) !!}
    @viteReactRefresh
    @vite('resources/js/app.tsx')
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
