<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($apariencia ?? 'system') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e3a5f">

    {{-- Evita el parpadeo claro/oscuro: fija la clase antes de pintar. --}}
    <script>
        (function () {
            const guardada = document.cookie.match(/(?:^|;\s*)apariencia=([^;]+)/)?.[1];
            const oscuro = guardada === 'dark'
                || ((!guardada || guardada === 'system')
                    && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', oscuro);
        })();
    </script>

    <title inertia>{{ config('app.name', 'Jichi') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-background font-sans antialiased">
    @inertia
</body>
</html>
