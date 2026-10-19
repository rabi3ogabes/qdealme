@props(['seo' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_dir() }}" data-theme="dark" class="overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#141114">
    <x-seo-head :seo="$seo" />
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme') || 'auto';
                var d = t === 'dark' || (t === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.theme = d ? 'dark' : 'light';
                document.documentElement.dataset.themeSetting = t;
            } catch (e) {}
        })();
    </script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('fonts/readex-pro-arabic.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/readex-pro-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-dvh overflow-hidden bg-bg text-text">
    <a href="#viewer-stage" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:start-3 focus:z-[100] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">
        {{ __('nav.skip_to_content') }}
    </a>
    {{ $slot }}
    <x-toasts />
</body>
</html>
